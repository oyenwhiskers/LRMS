<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Shelf;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StorageLabelController extends Controller
{
    public function rack(Request $request, Rack $rack): View
    {
        $this->authorizePrint($request);

        return view('storage.labels.print', ['kind' => 'rack', 'rack' => $rack, 'pdf' => false]);
    }

    public function rackPdf(Request $request, Rack $rack): Response
    {
        $this->authorizePrint($request);

        return Pdf::loadView('storage.labels.print', ['kind' => 'rack', 'rack' => $rack, 'pdf' => true])
            ->setPaper('a4', 'portrait')
            ->download($rack->code.'-rack-label.pdf');
    }

    public function shelf(Request $request, Shelf $shelf): View
    {
        $this->authorizePrint($request);

        return view('storage.labels.print', $this->shelfData($shelf, false));
    }

    public function shelfPdf(Request $request, Shelf $shelf): Response
    {
        $this->authorizePrint($request);

        return Pdf::loadView('storage.labels.print', $this->shelfData($shelf, true))
            ->setPaper([0, 0, 226.77, 113.39])
            ->download($shelf->rack->code.'-'.$shelf->code.'-shelf-label.pdf');
    }

    public function shelves(Request $request, Rack $rack): View
    {
        $this->authorizePrint($request);

        return view('storage.labels.print', $this->allShelvesData($rack, false));
    }

    public function shelvesPdf(Request $request, Rack $rack): Response
    {
        $this->authorizePrint($request);

        return Pdf::loadView('storage.labels.print', $this->allShelvesData($rack, true))
            ->setPaper([0, 0, 226.77, 113.39])
            ->download($rack->code.'-shelf-labels.pdf');
    }

    private function authorizePrint(Request $request): void
    {
        abort_unless($request->user()->can('storage.view') && $request->user()->can('labels.print'), 403);
    }

    private function shelfData(Shelf $shelf, bool $pdf): array
    {
        $shelf->load('rack');

        return [
            'kind' => 'shelf',
            'rack' => $shelf->rack,
            'labels' => [['shelf' => $shelf, 'qr' => $this->qr($shelf)]],
            'pdf' => $pdf,
            'download' => route('storage.shelves.label.pdf', $shelf),
        ];
    }

    private function allShelvesData(Rack $rack, bool $pdf): array
    {
        $rack->load(['shelves' => fn ($query) => $query->orderBy('code')]);
        abort_if($rack->shelves->isEmpty(), 404, 'This rack has no shelves to print.');

        return [
            'kind' => 'shelf',
            'rack' => $rack,
            'labels' => $rack->shelves->map(fn (Shelf $shelf) => ['shelf' => $shelf, 'qr' => $this->qr($shelf)]),
            'pdf' => $pdf,
            'download' => route('storage.racks.shelves-labels.pdf', $rack),
        ];
    }

    private function qr(Shelf $shelf): string
    {
        return (new SvgWriter)->write(new QrCode(data: $shelf->qrPayload(), size: 240, margin: 12))->getDataUri();
    }
}
