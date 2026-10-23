<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAiTokenUsageUnlocked;
use App\Models\AiTokenUsage;
use App\Models\User;
use App\Support\AiTokenCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiTokenUsageController extends Controller
{
    public function unlockForm(Request $request): View|RedirectResponse
    {
        if ((int) $request->session()->get(EnsureAiTokenUsageUnlocked::SESSION_KEY) === (int) $request->user()->id) {
            return redirect()->route('entry.ai-usage.index');
        }

        return view('entry.ai-usage.unlock');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:100'],
        ]);

        $expected = (string) config('lrms.ai_usage_password');
        if ($expected === '' || ! hash_equals($expected, $data['password'])) {
            return back()->withErrors(['password' => 'Kata laluan salah.']);
        }

        $request->session()->put(EnsureAiTokenUsageUnlocked::SESSION_KEY, $request->user()->id);

        return redirect()->route('entry.ai-usage.index');
    }

    public function index(Request $request): View
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        $filters = $request->validate([
            'period' => ['nullable', 'in:today,month,date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date'],
        ]);

        $period = $filters['period'] ?? 'today';
        $monthValue = $filters['month'] ?? $now->format('Y-m');
        $dateValue = $filters['date'] ?? $now->toDateString();

        $query = AiTokenUsage::query();
        $this->applyPeriod($query, $period, $monthValue, $dateValue, $tz);

        $chunks = (clone $query)
            ->select(
                'user_id',
                'model',
                DB::raw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens'),
                DB::raw('COALESCE(SUM(candidates_tokens), 0) as candidates_tokens'),
                DB::raw('COALESCE(SUM(thoughts_tokens), 0) as thoughts_tokens'),
                DB::raw('COALESCE(SUM(cached_tokens), 0) as cached_tokens'),
            )
            ->groupBy('user_id', 'model')
            ->get();

        $inputTokens = 0;
        $outputTokens = 0;
        $costMyr = 0.0;
        $byOperator = [];

        foreach ($chunks as $chunk) {
            $input = AiTokenCost::inputTokens((int) $chunk->prompt_tokens);
            $output = AiTokenCost::outputTokens((int) $chunk->candidates_tokens, (int) $chunk->thoughts_tokens);
            $cost = AiTokenCost::costMyr(
                (string) $chunk->model,
                (int) $chunk->prompt_tokens,
                (int) $chunk->candidates_tokens,
                (int) $chunk->thoughts_tokens,
                (int) $chunk->cached_tokens,
            );

            $inputTokens += $input;
            $outputTokens += $output;
            $costMyr += $cost;

            $key = (string) ($chunk->user_id ?? 'none');
            if (! isset($byOperator[$key])) {
                $byOperator[$key] = [
                    'user_id' => $chunk->user_id,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'cost_myr' => 0.0,
                ];
            }

            $byOperator[$key]['input_tokens'] += $input;
            $byOperator[$key]['output_tokens'] += $output;
            $byOperator[$key]['cost_myr'] += $cost;
        }

        $users = User::query()
            ->whereIn('id', collect($byOperator)->pluck('user_id')->filter()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $operators = collect($byOperator)
            ->map(function (array $row) use ($users) {
                $user = $row['user_id'] ? $users->get($row['user_id']) : null;

                return [
                    'name' => $user?->name ?? 'Tidak diketahui',
                    'email' => $user?->email,
                    'input_tokens' => $row['input_tokens'],
                    'output_tokens' => $row['output_tokens'],
                    'cost_myr' => $row['cost_myr'],
                ];
            })
            ->sortByDesc('cost_myr')
            ->values();

        $periodLabel = match ($period) {
            'month' => Carbon::createFromFormat('Y-m', $monthValue, $tz)->translatedFormat('F Y'),
            'date' => Carbon::parse($dateValue, $tz)->translatedFormat('d M Y'),
            default => 'Hari ini',
        };

        return view('entry.ai-usage.index', [
            'period' => $period,
            'monthValue' => $monthValue,
            'dateValue' => $dateValue,
            'periodLabel' => $periodLabel,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'costMyr' => $costMyr,
            'operators' => $operators,
        ]);
    }

    private function applyPeriod(Builder $query, string $period, string $monthValue, string $dateValue, string $tz): void
    {
        if ($period === 'month') {
            $start = Carbon::createFromFormat('Y-m', $monthValue, $tz)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);

            return;
        }

        if ($period === 'date') {
            $day = Carbon::parse($dateValue, $tz);
            $query->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);

            return;
        }

        $today = Carbon::now($tz);
        $query->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);
    }
}
