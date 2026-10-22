<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompletionController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->can('completions.review'), 403);

        $isFeed = $request->boolean('feed');
        $perPage = $isFeed ? 10 : 20;
        $query = LegalFile::query()
            ->active()
            ->where('status', LegalFile::STATUS_PENDING)
            ->with(['personInCharge', 'caseCategory']);

        if ($search = trim((string) $request->input('search'))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function ($builder) use ($contains): void {
                $builder->where('reference_number', 'like', $contains)
                    ->orWhere('file_identifier', 'like', $contains)
                    ->orWhere('purchaser', 'like', $contains)
                    ->orWhereHas('personInCharge', fn ($staff) => $staff->where('full_name', 'like', $contains))
                    ->orWhereHas('caseCategory', fn ($category) => $category->where('code', 'like', $contains));
            });
        }

        $files = $query
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        if ($isFeed) {
            return response()->json([
                'html' => view('completions.partials.mobile-completion-cards', [
                    'files' => $files,
                ])->render(),
                'next_page' => $files->hasMorePages() ? $files->currentPage() + 1 : null,
            ]);
        }

        return view('completions.index', compact('files'));
    }

    public function request(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('completions.request'), 403);
        abort_unless(
            $file->status === LegalFile::STATUS_REGISTERED,
            422,
            'Only registered files can be submitted for completion.',
        );

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transition(
            $file,
            LegalFile::STATUS_PENDING,
            'completion_requested',
            $data['notes'] ?? null,
            $request,
        );

        return back()->with('success', 'Completion requested. Waiting for boss review.');
    }

    public function approve(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('completions.review'), 403);
        abort_unless(
            $file->status === LegalFile::STATUS_PENDING,
            422,
            'Only pending files can be approved.',
        );

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transition(
            $file,
            LegalFile::STATUS_COMPLETED,
            'completion_approved',
            $data['notes'] ?? null,
            $request,
        );

        return back()->with('success', 'Completion approved. File is ready for storage.');
    }

    public function reject(Request $request, LegalFile $file): RedirectResponse
    {
        abort_unless($request->user()->can('completions.review'), 403);
        abort_unless(
            $file->status === LegalFile::STATUS_PENDING,
            422,
            'Only pending files can be rejected.',
        );

        $data = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $this->transition(
            $file,
            LegalFile::STATUS_REGISTERED,
            'completion_rejected',
            $data['notes'],
            $request,
        );

        return back()->with('success', 'Completion rejected. File returned to registered.');
    }

    private function transition(
        LegalFile $file,
        string $status,
        string $type,
        ?string $notes,
        Request $request,
    ): void {
        DB::transaction(function () use ($file, $status, $type, $notes, $request): void {
            $locked = LegalFile::query()->lockForUpdate()->findOrFail($file->id);

            FileMovement::create([
                'legal_file_id' => $locked->id,
                'type' => $type,
                'processed_by' => $request->user()->id,
                'shelf_id' => $locked->shelf_id,
                'previous_status' => $locked->status,
                'new_status' => $status,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);

            $locked->update([
                'status' => $status,
                'updated_by' => $request->user()->id,
            ]);
        });
    }
}
