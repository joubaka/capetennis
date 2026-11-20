<?php

namespace App\Services\Camps;

use App\Models\{CampCoachLink, CampCoachSubmission, CampConfiguration, CampNominee, Category, CategoryEvent, Event, Player, PracticeCampPlan, User};
use App\Services\PracticeCampPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CampWorkflow
{
    public function attach(User $actor, Event $event): PracticeCampPlan
    {
        app(PracticeCampPlanner::class)->authorizeManager($actor, $event);
        $existing = PracticeCampPlan::where('event_id', $event->id)->first();
        abort_unless($existing || $event->isCamp(), 422, 'Choose a Camp event.');

        return $existing ?? PracticeCampPlan::firstOrCreate(['camp_key' => 'camp-event-'.$event->id], [
            'event_id' => $event->id, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            'revision' => (string) Str::uuid(), 'candidates' => [], 'dates_provisional' => false, 'entry_fee' => $event->entryFee,
        ]);
    }

    public function settings(User $actor, PracticeCampPlan $plan, array $data): void
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);
        DB::transaction(function () use ($actor, $plan, $data) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $plan = PracticeCampPlan::lockForUpdate()->findOrFail($plan->id);
            abort_unless(hash_equals($plan->revision, $data['revision']), 409, 'The camp draft changed. Reload the settings.');
            $event = Event::lockForUpdate()->findOrFail($plan->event_id);
            abort_if(! app(CampAdmission::class)->configuration($event->id) && $event->signUp, 422, 'Close registration in the event settings before activating camp entry rules for the first time. Existing checkouts and payments are retained.');
            $campType = \App\Models\EventType::where('type', \App\Models\EventType::CAMP)->orderBy('id')->first()
                ?? \App\Models\EventType::where(fn ($q) => $q->whereNull('type')->orWhere('type', 0))->whereRaw('LOWER(TRIM(name)) = ?', ['camp'])->orderBy('id')->first();
            abort_unless($campType, 422, 'A recognised Camp event type is required before saving camp settings.');
            $existingIds = CategoryEvent::where('event_id', $event->id)->pluck('id');
            abort_if($existingIds->diff(collect($data['divisions'])->pluck('id')->filter())->isNotEmpty(), 422, 'Keep existing camp divisions so entry history and checkouts remain attached.');
            $divisions = [];
            foreach ($data['divisions'] as $input) {
                if (! empty($input['id'])) {
                    $division = CategoryEvent::where('event_id', $event->id)->lockForUpdate()->findOrFail($input['id']);
                } else {
                    $category = Category::firstOrCreate(['name' => $input['name']]);
                    $division = CategoryEvent::create(['event_id' => $event->id, 'category_id' => $category->id]);
                }
                if (($input['limit'] ?? null) !== null && app(\App\Services\DivisionCapacityService::class)->occupied($division, true) > (int) $input['limit']) {
                    throw ValidationException::withMessages(['divisions' => 'A division limit cannot be below its occupied or held places.']);
                }
                $division->update(['entry_fee' => $input['fee'], 'entry_limit' => $input['limit'] ?? null]);
                $divisions[$division->id] = ['min_age' => (int) $input['min_age'], 'max_age' => (int) $input['max_age'], 'gender' => (int) $input['gender']];
            }
            if (($data['capacity'] ?? null) !== null && count(app(CampAdmission::class)->occupied($event->id, true)) > (int) $data['capacity']) {
                throw ValidationException::withMessages(['capacity' => 'Capacity cannot be below the currently occupied or held places.']);
            }
            CampConfiguration::updateOrCreate(['event_id' => $event->id], [
                'method' => $data['method'], 'capacity' => $data['capacity'] ?? null, 'divisions' => $divisions,
                'registration_closes_at' => $data['registration_closes_at'] ?? null, 'coach_closes_at' => $data['coach_closes_at'] ?? null,
                'coach_min_age' => $data['coach_min_age'], 'coach_max_age' => $data['coach_max_age'],
                'revision' => (string) Str::uuid(), 'updated_by' => $actor->id,
            ]);
            // Never change publication/registration switches while saving camp configuration.
            $event->update(['eventType' => $campType->id, 'name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                'entryFee' => $data['entry_fee'], 'venue_notes' => $data['venue'], 'organizer' => $data['organizer'], 'email' => $data['email'],
                'withdrawal_deadline' => $data['withdrawal_deadline'] ?? $event->withdrawal_deadline]);
            $plan->update(['entry_fee' => $data['entry_fee'], 'capacity' => $data['capacity'] ?? null,
                'dates_provisional' => $data['dates_provisional'] ?? false, 'revision' => (string) Str::uuid(), 'updated_by' => $actor->id]);
            activity('camp')->performedOn($plan)->causedBy($actor)->log('Saved camp configuration');
        });
    }

    public function approve(User $actor, PracticeCampPlan $plan, int $playerId, int $divisionId, string $provenance): CampNominee
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);

        return DB::transaction(function () use ($actor, $plan, $playerId, $divisionId, $provenance) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $division = CategoryEvent::where('event_id', $plan->event_id)->lockForUpdate()->findOrFail($divisionId);
            $config = app(CampAdmission::class)->configuration($plan->event_id);
            abort_unless($config && isset($config->divisions[$division->id]), 422, 'Configure the camp division first.');
            $player = Player::whereKey($playerId)->sharedLock()->firstOrFail();
            $rules = $config->divisions[$division->id];
            $age = $division->event->start_date->year - \Carbon\Carbon::parse($player->dateOfBirth)->year;
            abort_unless($player->dateOfBirth && $age >= $rules['min_age'] && $age <= $rules['max_age']
                && ((int) $rules['gender'] === 0 || (int) $rules['gender'] === (int) $player->gender), 422, 'The player does not match the division age or gender rules.');
            $token = Str::random(64);
            $nominee = CampNominee::firstOrCreate(['event_id' => $plan->event_id, 'category_event_id' => $divisionId, 'player_id' => $playerId], [
                'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'provenance' => $provenance,
                'token_hash' => hash('sha256', $token), 'token_encrypted' => $token,
            ]);
            abort_if($nominee->status !== 'approved', 422, 'This nomination has a recorded decision. Review it separately before reapproving.');
            activity('camp')->performedOn($nominee)->causedBy($actor)->log('Approved camp nominee');

            return $nominee;
        });
    }

    public function coach(User $actor, PracticeCampPlan $plan, string $name, string $expires): string
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);
        $config = app(CampAdmission::class)->configuration($plan->event_id);
        abort_unless($config && $config->coach_closes_at && now()->lt($config->coach_closes_at), 422, 'Set an open coach nomination deadline first.');
        abort_if(\Carbon\Carbon::parse($expires)->gt($config->coach_closes_at), 422, 'The coach link must expire by the nomination deadline.');
        $token = Str::random(64);
        CampCoachLink::create(['event_id' => $plan->event_id, 'coach_name' => $name, 'expires_at' => $expires,
            'token_hash' => hash('sha256', $token), 'created_by' => $actor->id]);

        return route('camp.coach.show', $token);
    }

    public function coachByToken(string $token): CampCoachLink
    {
        abort_unless(strlen($token) === 64, 404);
        $link = CampCoachLink::where('token_hash', hash('sha256', $token))->firstOrFail();
        $config = app(CampAdmission::class)->configuration($link->event_id);
        abort_unless(! $link->revoked_at && now()->lt($link->expires_at) && $config?->coach_closes_at && now()->lte($config->coach_closes_at), 410, 'This nomination link has closed.');

        return $link;
    }

    public function submitCoach(string $token, array $data): void
    {
        $reference = $this->coachByToken($token);
        DB::transaction(function () use ($reference, $data) {
            $link = CampCoachLink::lockForUpdate()->findOrFail($reference->id);
            $config = app(CampAdmission::class)->configuration($link->event_id);
            abort_unless(! $link->revoked_at && now()->lt($link->expires_at) && $config?->coach_closes_at && now()->lte($config->coach_closes_at), 410, 'This nomination link has closed.');
            if (CampCoachSubmission::where('coach_link_id', $link->id)->where('request_token', $data['request_token'])->exists()) return;
            $config = app(CampAdmission::class)->configuration($link->event_id);
            $age = $link->event->start_date->year - 1 - \Carbon\Carbon::parse($data['date_of_birth'])->year;
            abort_unless($age >= $config->coach_min_age && $age <= $config->coach_max_age, 422, 'The player is outside the coach nomination age range.');
            abort_if(CampCoachSubmission::where('coach_link_id', $link->id)->count() >= 100, 422, 'This coach link has reached its submission limit.');
            CampCoachSubmission::firstOrCreate(['coach_link_id' => $link->id, 'request_token' => $data['request_token']], [
                'event_id' => $link->event_id, 'name' => $data['name'], 'surname' => $data['surname'],
                'date_of_birth' => $data['date_of_birth'], 'gender' => $data['gender'], 'guardian_name' => $data['guardian_name'],
                'guardian_email' => mb_strtolower(trim($data['guardian_email'])), 'reason' => $data['reason'] ?? null,
            ]);
        });
    }

    public function reviewCoach(User $actor, PracticeCampPlan $plan, CampCoachSubmission $submission, array $data): void
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);
        abort_unless((int) $submission->event_id === (int) $plan->event_id, 404);
        DB::transaction(function () use ($actor, $plan, $submission, $data) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $submission = CampCoachSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($submission->status !== 'pending') return;
            if ($data['decision'] === 'reject') {
                $submission->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
                return;
            }
            $player = isset($data['player_id']) ? Player::whereKey($data['player_id'])->sharedLock()->firstOrFail() : null;
            if (! $player && ! empty($data['create_new'])) {
                $exists = Player::whereDate('dateOfBirth', $submission->date_of_birth)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($submission->name))])
                    ->whereRaw('LOWER(TRIM(surname)) = ?', [mb_strtolower(trim($submission->surname))])->lockForUpdate()->first(['id']);
                abort_if($exists, 422, 'A matching player exists. Resolve the submission to that profile instead.');
                $player = Player::create(['name' => $submission->name, 'surname' => $submission->surname,
                    'dateOfBirth' => $submission->date_of_birth->toDateString(), 'gender' => $submission->gender, 'email' => $submission->guardian_email]);
            }
            $normalize = fn ($text) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
            abort_unless($player && $player->dateOfBirth && $normalize($player->name) === $normalize($submission->name)
                && $normalize($player->surname) === $normalize($submission->surname) && \Carbon\Carbon::parse($player->dateOfBirth)->isSameDay($submission->date_of_birth)
                && (int) $player->gender === (int) $submission->gender, 422, 'Resolve to the exact player with matching date of birth and gender.');
            $nominee = $this->approve($actor, $plan, $player->id, (int) ($data['division_id'] ?? 0), 'Coach submission '.$submission->id);
            $submission->update(['status' => 'approved', 'player_id' => $player->id, 'nominee_id' => $nominee->id,
                'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }
}
