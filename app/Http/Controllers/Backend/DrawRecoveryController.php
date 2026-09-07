<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawRecoveryCase;
use App\Models\Fixture;
use App\Services\Draw\DrawRecoveryService;
use Illuminate\Http\Request;

class DrawRecoveryController extends Controller
{
    public function preview(Request $request, Draw $draw, DrawRecoveryService $recovery)
    {
        $this->authorize('progress', $draw);
        $data = $request->validate([
            'fixture_id' => 'required|integer|exists:fixtures,id',
        ]);
        $fixture = $draw->drawFixtures()->findOrFail($data['fixture_id']);

        return response()->json(['success' => true] + $recovery->preview($draw, $fixture));
    }

    public function apply(Request $request, Draw $draw, DrawRecoveryService $recovery)
    {
        $this->authorize('progress', $draw);
        $data = $request->validate([
            'fixture_id' => 'required|integer|exists:fixtures,id',
            'sets' => 'required|array|min:1|max:20',
            'sets.*' => 'required|array|size:2',
            'sets.*.*' => 'required|integer|min:0|max:999',
            'reason' => 'required|string|min:10|max:2000',
            'fingerprint' => 'required|string|size:64',
            'confirmation' => 'required|string',
        ]);
        abort_unless(hash_equals('RECOVER #'.$draw->id, $data['confirmation']), 422,
            'Type RECOVER #'.$draw->id.' exactly to confirm this correction.');

        $fixture = $draw->drawFixtures()->findOrFail($data['fixture_id']);
        $preview = $recovery->preview($draw, $fixture);
        if ($preview['impact']['requires_super_user']) {
            abort_unless($request->user()->hasRole('super-user'), 403,
                'A super-user must apply a recovery that resets played playoff matches.');
        }

        $case = $recovery->apply(
            $draw,
            $fixture,
            $data['sets'],
            $data['reason'],
            $data['fingerprint'],
        );

        return response()->json([
            'success' => true,
            'message' => 'The corrected round-robin result was saved. Playoffs were reset from the snapshot; review the standings, then unlock and progress the draw again.',
            'recovery_case' => $case,
        ]);
    }

    public function restore(Request $request, Draw $draw, DrawRecoveryCase $recoveryCase, DrawRecoveryService $recovery)
    {
        abort_unless((int) $recoveryCase->draw_id === (int) $draw->id, 404);
        abort_unless($request->user()->hasRole('super-user'), 403,
            'Only a super-user may restore a tournament recovery snapshot.');
        $data = $request->validate([
            'confirmation' => 'required|string',
            'reason' => 'required|string|min:10|max:2000',
        ]);
        abort_unless(hash_equals('RESTORE #'.$recoveryCase->id, $data['confirmation']), 422,
            'Type RESTORE #'.$recoveryCase->id.' exactly to confirm this restore.');

        $case = $recovery->restore($recoveryCase, $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'The snapshot was restored. The draw remains locked and unpublished for review.',
            'recovery_case' => $case,
        ]);
    }
}
