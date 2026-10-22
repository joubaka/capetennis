<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventRegion;
use App\Models\EventRegionFinancialRecipientLink;
use App\Models\EventRegionManager;
use App\Models\User;
use App\Services\TeamSelection\RegionManagerAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Event-only financial identity confirmation; never grants account access. */
class EventRegionFinancialRecipientLinkService
{
    public function record(Event $event, User $actor, array $input, bool $apply = false): array
    {
        $this->assertLocalDatabase();
        $actor = User::findOrFail($actor->id);
        abort_unless($actor->hasRole('super-user'), 403);
        $data = Validator::make($input, [
            'event_region_id' => ['required', 'integer', 'min:1'],
            'source_manager_user_id' => ['required', 'integer', 'min:1'],
            'target_convenor_id' => ['required', 'integer', 'min:1'],
            'target_user_id' => ['required', 'integer', 'min:1'],
            'confirmed_same_person' => ['required', 'accepted'],
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'request_token' => ['required', 'uuid'],
        ])->validate();
        abort_unless((int) $actor->id === (int) $data['target_user_id'], 403, 'Confirm this financial link from the target director account.');
        $normalized = [
            'event_id' => $event->id, 'event_region_id' => (int) $data['event_region_id'],
            'source_manager_user_id' => (int) $data['source_manager_user_id'],
            'target_convenor_id' => (int) $data['target_convenor_id'], 'target_user_id' => (int) $data['target_user_id'],
            'confirmed_same_person' => true, 'evidence' => trim($data['evidence']),
        ];
        if (mb_strlen($normalized['evidence']) < 10) $this->invalid('evidence', 'Record the explicit same-person confirmation and its context.');
        $fingerprint = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $actor, $data, $normalized, $fingerprint, $apply) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $scope = EventRegion::where('event_id', $event->id)->whereKey($data['event_region_id'])->lockForUpdate()->first();
            if (! $scope || ! $scope->region
                || EventRegion::where('event_id', $event->id)->where('region_id', $scope->region_id)->lockForUpdate()->get()->count() !== 1) {
                $this->invalid('event_region_id', 'Choose an unambiguous region belonging to this event.');
            }
            $assignments = EventRegionManager::with('user')->where('event_region_id', $scope->id)->lockForUpdate()->get();
            if ($assignments->count() > 1 || $assignments->contains(fn ($assignment) =>
                (int) $assignment->event_id !== (int) $event->id || (int) $assignment->region_id !== (int) $scope->region_id || ! $assignment->user)) {
                $this->invalid('source_manager_user_id', 'The current canonical regional convenor assignment is inconsistent.');
            }
            $manager = app(RegionManagerAccessService::class)->manager($scope);
            if (! $manager || (int) $manager->id !== (int) $data['source_manager_user_id']
                || ($assignments->isNotEmpty() && (int) $assignments->first()->user_id !== (int) $manager->id)) {
                $this->invalid('source_manager_user_id', 'The source must be the exact current canonical regional convenor account.');
            }
            $target = EventConvenor::with('user')->where('event_id', $event->id)->whereKey($data['target_convenor_id'])
                ->where('role', '!=', 'score-keeper')->active()->lockForUpdate()->first();
            if (! $target || ! $target->user || (int) $target->user_id !== (int) $data['target_user_id']
                || EventConvenor::where('event_id', $event->id)->where('user_id', $target->user_id)->where('role', '!=', 'score-keeper')->count() !== 1) {
                $this->invalid('target_convenor_id', 'Choose the exact active financial director record for the confirming account in this event.');
            }
            if ((int) $manager->id === (int) $target->user_id
                || EventConvenor::where('event_id', $event->id)->where('user_id', $manager->id)->where('role', '!=', 'score-keeper')->exists()) {
                $this->invalid('source_manager_user_id', 'This source already has a financial director identity; an alternate recipient link would be conflicting.');
            }
            $existingToken = EventRegionFinancialRecipientLink::where('request_token', $data['request_token'])->lockForUpdate()->first();
            if ($existingToken) {
                if (! hash_equals($existingToken->request_fingerprint, $fingerprint)) $this->invalid('request_token', 'This confirmation was already used with different financial recipient details.');
                return ['link' => $existingToken, 'created' => false, 'preview' => ! $apply];
            }
            if (EventRegionFinancialRecipientLink::where('event_id', $event->id)->where('region_id', $scope->region_id)->exists()) {
                $this->invalid('event_region_id', 'An immutable financial recipient confirmation already exists for this event and physical region. Review it before any further change.');
            }
            if (! $apply) return ['link' => null, 'created' => false, 'preview' => true];
            $link = EventRegionFinancialRecipientLink::create([
                'event_id' => $event->id, 'event_region_id' => $scope->id, 'region_id' => $scope->region_id,
                'source_manager_user_id' => $manager->id, 'target_convenor_id' => $target->id, 'target_user_id' => $target->user_id,
                'source_name' => $manager->name, 'target_name' => $target->user->name,
                'confirmed_by' => $actor->id, 'confirmed_by_name' => $actor->name, 'evidence' => $normalized['evidence'],
                'request_token' => $data['request_token'], 'request_fingerprint' => $fingerprint, 'confirmed_at' => now(),
            ]);
            activity('clothing-finance')->performedOn($link)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'region_id' => $scope->region_id, 'source_manager_user_id' => $manager->id,
                'target_convenor_id' => $target->id, 'target_user_id' => $target->user_id,
                'account_access_changed' => false, 'money_moved' => false,
            ])->log('Event regional financial recipient confirmed as the same person');
            return ['link' => $link, 'created' => true, 'preview' => false];
        });
    }

    private function assertLocalDatabase(): void
    {
        $connection = DB::connection();
        $hostIsLocal = in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true);
        $isolatedTest = app()->runningUnitTests() && app()->environment('testing')
            && (($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:')
                || ($connection->getDriverName() === 'mysql' && $hostIsLocal && preg_match('/^ct_testing(?:_|$)/', $connection->getDatabaseName())));
        $local = app()->environment('local') && $connection->getDriverName() === 'mysql' && $hostIsLocal && $connection->getDatabaseName() === 'ct';
        abort_unless($isolatedTest || $local, 403, 'Financial recipient confirmation is restricted to the local ct database and isolated tests.');
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
