<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests { authorize as private authorizeRequest; }
    use DispatchesJobs, ValidatesRequests;

    /** Flexible graphs must not be mutated by legacy slot-order endpoints. */
    public function authorize($ability, $arguments = [])
    {
        $response = $this->authorizeRequest($ability, $arguments);
        $venueUpdate = in_array($ability, ['update', 'fixture.update'], true) && request()->routeIs(
            'backend.draw.venues.store', 'add.venue.draw', 'remove.venue.draw', 'save.draw.venues'
        );
        // Only these controllers accept a name-only payload; structural fields stay guarded.
        $nameUpdate = $ability === 'update' && request()->routeIs('backend.draw.update-settings', 'draws.update')
            && array_keys(request()->except(['_token', '_method'])) === ['name'];
        if (! $venueUpdate && ! $nameUpdate && ! request()->routeIs('flexible-monrad.*', 'draw.setup.store') && in_array($ability, [
            'update', 'fixture.update', 'modifyGroups', 'generateFixtures', 'generateBrackets', 'saveScore', 'deleteScore', 'publish',
        ], true)) {
            foreach (is_array($arguments) ? $arguments : [$arguments] as $argument) {
                $draw = $argument instanceof \App\Models\Draw ? $argument
                    : ($argument instanceof \App\Models\Fixture ? $argument->draw : null);
                abort_if($draw?->usesFlexibleMonrad(), 409, 'Use the Flexible Monrad editor to change this draw.');
            }
        }
        return $response;
    }
}
