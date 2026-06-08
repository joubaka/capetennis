<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Doubles\Services\PairService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\RegistrationPair;
use Illuminate\Http\Request;

/**
 * AdminDoublesController
 *
 * Admin-only doubles pair management.
 *
 * PHASE 2 SCOPE:
 *   - List pairs for a category event
 *   - Create pair (player1 + player2 + category event)
 *   - Remove pair (if not locked / published)
 *
 * OUT OF SCOPE: invitations, split payments, refunds, public registration.
 */
class AdminDoublesController extends Controller
{
    public function __construct(private PairService $pairService) {}

    // -----------------------------------------------------------------------
    // LIST
    // -----------------------------------------------------------------------

    /**
     * Return pairs for a category event (JSON — consumed by the Pairs tab).
     */
    public function index(CategoryEvent $categoryEvent)
    {
        $pairs = $this->pairService->activePairsFor($categoryEvent);

        $rows = $pairs->map(function (RegistrationPair $pair) use ($categoryEvent) {
            $reg        = $pair->registration;
            $players    = $reg->players->sortBy([['surname', 'asc'], ['name', 'asc']]);
            $inDraw     = $reg->draws()->exists();

            return [
                'id'            => $pair->id,
                'registration_id' => $pair->registration_id,
                'pair_name'     => $reg->displayName(),
                'players'       => $players->map(fn ($p) => [
                    'id'      => $p->id,
                    'name'    => $p->name,
                    'surname' => $p->surname,
                ])->values(),
                'status'        => $pair->status,
                'in_draw'       => $inDraw,
                'can_remove'    => ! $categoryEvent->isLocked() && ! $inDraw,
            ];
        });

        return response()->json(['pairs' => $rows]);
    }

    // -----------------------------------------------------------------------
    // CREATE
    // -----------------------------------------------------------------------

    public function store(Request $request, CategoryEvent $categoryEvent)
    {
        $data = $request->validate([
            'player1_id' => ['required', 'integer', 'exists:players,id'],
            'player2_id' => ['required', 'integer', 'exists:players,id'],
        ]);

        try {
            $pair = $this->pairService->createPair(
                $categoryEvent,
                (int) $data['player1_id'],
                (int) $data['player2_id'],
                auth()->user()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $pair->load('registration.players');

        $reg     = $pair->registration;
        $cer     = $reg->categoryEventRegistrations()->where('category_event_id', $categoryEvent->id)->first();
        $pairName = $reg->displayName();

        $row = view('backend.event.individual.partials.pair-row', [
            'reg'      => $cer,
            'pairName' => $pairName,
            'loop'     => (object) ['iteration' => null],
        ])->render();

        return response()->json([
            'success'    => true,
            'pair_id'    => $pair->id,
            'pair_name'  => $pairName,
            'row'        => $row,
            'message'    => 'Pair created successfully.',
        ], 201);
    }

    // -----------------------------------------------------------------------
    // REMOVE
    // -----------------------------------------------------------------------

    public function destroy(CategoryEvent $categoryEvent, RegistrationPair $pair)
    {
        // Ensure the pair belongs to this category event
        if ($pair->category_event_id !== $categoryEvent->id) {
            return response()->json(['success' => false, 'message' => 'Pair not found in this category.'], 404);
        }

        try {
            $this->pairService->removePair($pair, auth()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Pair removed.']);
    }

    // -----------------------------------------------------------------------
    // ELIGIBLE PLAYERS
    // -----------------------------------------------------------------------

    /**
     * Return players not yet paired in this category (for dropdowns).
     */
    public function eligiblePlayers(CategoryEvent $categoryEvent)
    {
        $players = $this->pairService->eligiblePlayers($categoryEvent);

        return response()->json(['players' => $players]);
    }
}
