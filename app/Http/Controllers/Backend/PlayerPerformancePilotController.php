<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Player;
use App\Services\Performance\PlayerPerformancePilotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlayerPerformancePilotController extends Controller
{
    public function directory(Request $request)
    {
        $settings = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($settings['search'] ?? '');
        $query = Player::query()->select(['id', 'name', 'surname']);
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token).'%';
            $query->where(fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("surname LIKE ? ESCAPE '!'", [$like]));
        }
        $players = $query->orderBy('surname')->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
        return view('backend.player-performance.directory', compact('players', 'search'));
    }

    public function show(Player $player, PlayerPerformancePilotService $service)
    {
        $performance = $service->forPlayer($player);
        return view('backend.player-performance.show', compact('player', 'performance'));
    }

    public function index(Request $request, PlayerPerformancePilotService $service)
    {
        $settings = $request->validate([
            'a_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'b_category_id' => ['nullable', 'integer', 'exists:categories,id', 'different:a_category_id'],
            'discipline' => ['nullable', Rule::in(['singles', 'doubles'])],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'as_of' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        // B is optional; a B-only preview has no defined comparison band.
        if ($request->filled('b_category_id') && !$request->filled('a_category_id')) {
            throw ValidationException::withMessages([
                'a_category_id' => 'Choose the comparable A category first.',
            ]);
        }
        $settings['discipline'] = $settings['discipline'] ?? 'singles';
        $settings['months'] = $settings['months'] ?? 12;
        $settings['as_of'] = $settings['as_of'] ?? today()->toDateString();
        $tiers = $request->filled('a_category_id') ? [(int) $settings['a_category_id'] => 'A'] : [];
        if ($request->filled('b_category_id')) {
            $tiers[(int) $settings['b_category_id']] = 'B';
        }
        $preview = $tiers ? $service->preview(
            $tiers,
            $settings['discipline'],
            CarbonImmutable::parse($settings['as_of']),
            (int) $settings['months'],
        ) : null;
        $all = $preview['players'] ?? collect();
        $players = new LengthAwarePaginator(
            $all->forPage($request->integer('page', 1), 20)->values(),
            $all->count(),
            20,
            $request->integer('page', 1),
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $categories = Category::query()->orderBy('name')->orderBy('id')->limit(501)->get(['id', 'name']);

        return view('backend.player-performance.index', compact('settings', 'preview', 'players', 'categories'));
    }
}
