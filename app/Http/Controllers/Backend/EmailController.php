<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\Registration;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\Player;
use App\Models\Series;
use App\Services\MailAccountManager;
use App\Services\BulkMailDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\SiteSetting;
use App\Models\TeamSelectionInvitation;


class EmailController extends Controller
{
  private array $dispatchStats = ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => 0];
  private ?string $campaignKey = null;
  private ?string $reportUrl = null;
  private array $reportUrls = [];

  private function composeDispatch(string $mailType, $related, array $recipients, array $details): array
  {
    $this->campaignKey ??= (string) \Illuminate\Support\Str::uuid();
    if ($related instanceof TeamRegion) {
      $scopeEvent = Event::findOrFail($details['event'] ?? 0);
      abort_unless($scopeEvent->regions()->whereKey($related->id)->exists(), 404);
    }
    $campaign = hash('sha256', ($details['campaign_key'] ?? $this->campaignKey).'|'.auth()->id());
    if (! empty($details['event'])) $this->reportUrl = route('backend.event-mail-log.index', ['event' => $details['event'], 'campaign' => $campaign]);
    $stats = app(BulkMailDispatcher::class)->dispatch($mailType, $related, $recipients, [
      'campaign_key' => $campaign,
      'event_id' => ! empty($details['event']) ? (int) $details['event'] : null, 'created_by' => auth()->id(),
      'team_id' => $related instanceof Team ? $related->id : null, 'region_id' => $related instanceof TeamRegion ? $related->id : ($related instanceof Team ? $related->region_id : null),
      'subject' => $details['subject'], 'message' => $details['message'],
      'from_name' => $details['fromName'], 'reply_to' => $details['replyTo'],
      'recipient_kind' => $details['recipient_kind'] ?? 'players', 'manual_retry_only' => true,
    ]);
    if (($details['recipient_kind'] ?? 'players') === 'players') {
      foreach ($this->dispatchStats as $key => $value) $this->dispatchStats[$key] += $stats[$key];
      if (! empty($details['event'])) {
        $eventId = (int) $details['event'];
        $report = $this->reportUrls[$eventId] ?? ['event_id' => $eventId, 'url' => $this->reportUrl, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (['queued', 'skipped', 'failed'] as $key) $report[$key] += $stats[$key];
        $this->reportUrls[$eventId] = $report;
      }
    }
    return $stats;
  }

  private function dispatchResult(): array
  {
    $stats = $this->dispatchStats;
    return [...$stats, 'report_url' => count($this->reportUrls) > 1 ? null : $this->reportUrl, 'report_urls' => array_values($this->reportUrls), 'title' => $stats['queued'] === 0 ? 'error' : (($stats['skipped'] || $stats['failed']) ? 'warning' : 'success'),
      'message' => "{$stats['queued']} recipient emails queued; {$stats['skipped']} skipped (invalid: {$stats['invalid']}, duplicate: {$stats['duplicate']}); {$stats['failed']} could not be queued. Check the event Email Log for results."];
  }


  public function sendEmail(Request $request)
  {
    $this->dispatchStats = array_fill_keys(array_keys($this->dispatchStats), 0);
    $this->reportUrl = null;
    $this->reportUrls = [];
    $request->validate(['event_id' => 'required|integer|exists:events,id', 'campaign_key' => 'nullable|uuid', 'emailSubject' => 'required|string|max:255', 'message' => 'required|string']);
    $this->campaignKey = $request->campaign_key ?? (string) \Illuminate\Support\Str::uuid();
    // Resolve event and authorize
    $eventId = $request->event_id;
    if ($eventId) {
      $event = Event::findOrFail($eventId);
      $this->authorize('event-email.bulk-send', $event);
      if ($event->isTeam()) {
        return redirect()->route('backend.event-communications.index', $event)->with('success', 'Review recipients and the exact message in Communications before sending.');
      }
      if ($event->isInterprovincialTrials()) {
        return redirect()->route('backend.interprovincial-trials.communications.index', $event)->with('success', 'Review recipients and the exact message in Communications before sending.');
      }
    }

    // 🧩 Automatically pick mailer
    $mailer = app(MailAccountManager::class)->getMailer();

    Log::debug('[Mail] Incoming request', [
      'target_type' => $request->target_type,
      'to' => $request->to,
      'team_id' => $request->team_id,
      'event_id' => $request->event_id,
      'region_id' => $request->region_id,
      'catEvent' => $request->catEvent,
    ]);

    $details = [
      'campaign_key' => $this->campaignKey,
      'team' => $request->team_id,
      'event' => $request->event_id,
      'region' => $request->region_id,
      'categoryEvent' => $request->catEvent,
      'fromName' => trim($request->fromName ?? 'Cape Tennis Admin'),

      'fromEmail' => match ($mailer) {
        'noreply1' => 'noreply1@capetennis.co.za',
        'noreply2' => 'noreply2@capetennis.co.za',
        default => 'noreply@capetennis.co.za',
      },

      'replyTo' => filter_var($request->replyTo, FILTER_VALIDATE_EMAIL)
        ? $request->replyTo
        : (auth()->user()->email ?? 'info@capetennis.co.za'),

      'message' => $request->message,
      'bcc' => $request->bcc,
      'subject' => $request->emailSubject,
    ];

    Log::info('[Mail] Preparing email', [
      'mailer' => $mailer,
      'subject' => $details['subject'],
      'from' => $details['fromEmail'],
      'to' => $request->to,
      'target' => $request->target_type,
    ]);

    $recipient = $request->to;

    /*
    |--------------------------------------------------------------------------
    | 🧠 SINGLE PLAYER
    |--------------------------------------------------------------------------
    */
    if ($request->target_type === 'player' && is_numeric($recipient)) {

      Log::debug('[Mail] Route: SINGLE PLAYER', [
        'player_id' => $recipient,
      ]);

      $player = Player::whereKey($recipient)->where(function ($query) use ($event) {
        $query->whereHas('registrations.categoryEventRegistrations.categoryEvent', fn ($q) => $q->where('event_id', $event->id))
          ->orWhereHas('teams.category', fn ($q) => $q->where('event_id', $event->id))
          ->orWhereIn('id', EventNomination::where('event_id', $event->id)->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->whereNotNull('player_id')->select('player_id'))
          ->orWhereIn('id', TeamSelectionInvitation::where('event_id', $event->id)->whereHas('selectionImport', fn ($q) => $q->where('event_id', $event->id))->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->select('player_id'))
          ->orWhereIn('id', \App\Models\InterprovincialTrialInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->select('player_id'))
          ->orWhereIn('id', \App\Models\MastersInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->select('player_id'));
      })->first();

      if (!$player) {
        Log::warning('[Mail] Player not found', ['player_id' => $recipient]);
        return response()->json([
          'success' => false,
          'message' => 'Invalid player selected.'
        ], 404);
      }

      $details['email'] = trim(strtolower((string) $player->email));
      $result = $this->sendToIndividual($details, $mailer);

      Log::info('[Mail] Player email sent', [
        'player_id' => $player->id,
        'email' => $details['email'],
      ]);
      Log::info('[Mail] 🏁 COMPLETED REQUEST', [
        'target_type' => $request->target_type,
        'recipient' => $recipient,
        'subject' => $details['subject'],
        'mailer' => $mailer,
      ]);

      return response()->json([
        'success' => ($result['title'] ?? null) !== 'error',
        'mailer' => $mailer,
        'result' => $result,
      ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🧠 TEAM EMAIL
    |--------------------------------------------------------------------------
    */
    if ($request->target_type === 'team' && is_numeric($request->team_id)) {

      Log::debug('[Mail] Route: TEAM', [
        'team_id' => $request->team_id,
      ]);

      $result = $this->sendToTeam($details, $mailer);

      Log::info('[Mail] Team email completed', [
        'team_id' => $request->team_id,
        'result' => $result,
      ]);

      return response()->json([
        'success' => ($result['title'] ?? null) !== 'error',
        'mailer' => $mailer,
        'result' => $result,
      ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🎯 LEGACY / DROPDOWN RECIPIENTS
    |--------------------------------------------------------------------------
    */
    Log::debug('[Mail] Route: LEGACY', [
      'recipient' => $recipient,
    ]);

    switch ($recipient) {

      case 'All players in event':
        Log::debug('[Mail] Legacy: All players in event');
        $result = $this->sendToEventType($details, $mailer);
        break;

      // ✅ ADD THIS MISSING CASE
      case 'All players in team':
        Log::debug('[Mail] Legacy: All players in team', ['team_id' => $details['team']]);
        $result = $this->sendToTeam($details, $mailer);
        break;

      case 'All players in nominations':
      case 'All nominated players':
        Log::debug('[Mail] Legacy: Nominations');
        $result = $this->sendToNominations($details, $mailer);
        break;

      case 'All Unregistered players in Event':
        Log::debug('[Mail] Legacy: Unregistered event');
        $result = $this->sendToAllUnregisteredInEvent($details, $mailer);
        break;

      case 'All Unregistered players in Region':
        Log::debug('[Mail] Legacy: Unregistered region');
        $result = $this->sendToUnregisteredInRegion($details, $mailer);
        break;

      case 'All Unregistered players in Team':
        Log::debug('[Mail] Legacy: Unregistered team');
        $result = $this->sendToEventUnregisteredTeam($details, $mailer);
        break;

      case 'All players in region':
        Log::debug('[Mail] Legacy: Region');
        $result = $this->sendToRegion($details, $mailer);
        break;

      case 'All players in category':
        Log::debug('[Mail] Legacy: Category');
        $result = $this->sendToAllPlayersInCategory($details, $mailer);
        break;

      default:
        Log::debug('[Mail] Legacy: Direct email', [
          'email' => $recipient,
        ]);
        $details['email'] = trim(strtolower($recipient));
        $result = $this->sendToIndividual($details, $mailer);
        break;
    }

    Log::info('[Mail] ✅ Email batch complete', [
      'mailer' => $mailer,
      'to' => $recipient,
    ]);

    return response()->json([
      'success' => ($result['title'] ?? null) !== 'error',
      'mailer' => $mailer,
      'result' => $result,
    ]);
  }

  /**
   * ✅ Unified event-type handler (individual / team)
   */
  protected function sendToEventType(array $details, string $mailer)
  {  
    $event = Event::with('eventType', 'regions')->find($details['event']);
    if (!$event)
      return ['message' => 'Event not found', 'title' => 'error'];

    if ($event->eventType->type == 1) {

      return $this->sendToEvent($details, $mailer);
    } elseif ($event->eventType->type == 2) {
      foreach ($event->regions as $region) {
        $details['region'] = $region->id;
        $this->sendToRegion($details, $mailer);
      }
      return $this->dispatchResult();
    }

    return ['message' => 'Unsupported event type', 'title' => 'error'];
  }

  /** ✅ Individual player */
  public function sendToIndividual(array $details, string $mailer)
  {
    $this->queueMail($details, $mailer);
    $this->sendToOwner($details, $mailer);

    return $this->dispatchResult();
  }

  /** ✅ All players registered in event */


  public function sendToEvent(array $details, string $mailer)
  {
    Log::info('[sendToEvent] ▶️ START', [
      'event_id' => $details['event'] ?? null,
      'mailer' => $mailer,
      'subject' => $details['subject'] ?? '(no subject)'
    ]);

    $event = Event::with('registrations.players')->find($details['event']);

    if (!$event) {
      Log::warning('[sendToEvent] ❌ Event not found', ['event_id' => $details['event']]);
      return ['message' => 'Event not found.', 'title' => 'error'];
    }

    // Collect all player emails
    $recipients = [];
    $playerCount = 0;
    $missingEmail = 0;

    Log::info('[sendToEvent] 🟢 Event loaded', [
      'event_id' => $event->id,
      'event_name' => $event->name ?? null,
      'registrations_count' => $event->registrations->count()
    ]);

    foreach ($event->registrations as $registrationIndex => $registration) {
      $players = $registration->players ?? collect();
      Log::debug('[sendToEvent] 🔹 Processing registration', [
        'registration_index' => $registrationIndex + 1,
        'players_in_registration' => $players->count()
      ]);

      foreach ($players as $player) {
        $playerCount++;

        if (!empty($player->email)) {
          $recipients[] = trim(strtolower((string) $player->email));

          Log::debug('[sendToEvent] 📧 Collected email', [
            'player_id' => $player->id ?? null,
            'player_name' => "{$player->name} {$player->surname}",
            'email' => $player->email
          ]);
        } else {
          $recipients[] = ['email' => '', 'name' => $player->full_name];
          $missingEmail++;
          Log::warning('[sendToEvent] ⚠️ Player missing email', [
            'player_id' => $player->id ?? null,
            'player_name' => "{$player->name} {$player->surname}"
          ]);
        }
      }
    }

    // Use BulkMailDispatcher for throttled sending (prevents Exim 10-email limit)
    $recipientCount = count($recipients);
    $stats = $this->composeDispatch('event_email', $event, $recipients, $details);
    $queuedCount = $stats['queued'];

    // Send to event owner (if applicable)
    try {
      $this->sendToOwner($details, $mailer);
      Log::info('[sendToEvent] 📨 Sent copy to event owner');
    } catch (\Throwable $e) {
      Log::error('[sendToEvent] ❌ sendToOwner failed', ['error' => $e->getMessage()]);
    }

    Log::info('[sendToEvent] ✅ FINISHED', [
      'total_players' => $playerCount,
      'queued' => $queuedCount,
      'missing_email' => $missingEmail
    ]);

    return $this->dispatchResult();
  }

  /** ✅ All nominations */
  public function sendToNominations(array $details, string $mailer)
  {
    $eventId = $details['event'];
    $nominations = EventNomination::where('event_id', $eventId)->with('player')->get();

    // Collect all nomination emails
    $recipients = [];
    foreach ($nominations as $nom) {
      $recipients[] = ['email' => $nom->player?->email ?? $nom->nominee_email ?? '', 'name' => $nom->display_name];
    }

    // Use BulkMailDispatcher for throttled sending
    $recipientCount = count($recipients);
    $event = Event::find($eventId);
    $stats = $this->composeDispatch('nomination_email', $event, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return $this->dispatchResult();
  }

  /** ✅ Unpaid players in team */
  public function sendToEventUnregisteredTeam(array $details, string $mailer)
  {
    $event = Event::findOrFail($details['event'] ?? 0);
    $region = $event->regions()->whereKey($details['region'])->firstOrFail();
    $teams = $this->teamsForEmailScope($event, $region->id);

    // Collect unpaid player emails
    $recipients = [];
    foreach ($teams as $team) {
      foreach ($team->players as $p) {
        if ($p->pivot->pay_status == 0) {
          $recipients[] = trim(strtolower((string) $p->email));
        }
      }
    }

    $count = count($recipients);

    // Use BulkMailDispatcher for throttled sending
    $stats = $this->composeDispatch('unregistered_team_email', $region, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return $this->dispatchResult();
  }

  /** ✅ All players in team */
  public function sendToTeam(array $details, string $mailer)
  {
    $event = Event::findOrFail($details['event'] ?? 0);
    $team = $this->teamsForEmailScope($event)
      ->firstWhere('id', (int) ($details['team'] ?? 0));
    if (!$team)
      abort(404, 'Team does not belong to this event.');

    // ✅ Collect player emails
    $recipients = [];
    foreach ($team->players as $player) {
      $recipients[] = ['email' => $player->email ?? '', 'name' => $player->full_name];
    }
    $recipients = array_values($recipients);

    // ✅ Use BulkMailDispatcher for throttled sending (prevents Exim 10-email limit)
    $stats = $this->composeDispatch('team_email', $team, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    return $this->dispatchResult();
  }

  /** ✅ All players in region */
  public function sendToRegion(array $details, string $mailer)
  {
    Log::info('[sendToRegion] ▶️ START', [
      'region_id' => $details['region'] ?? null,
      'mailer' => $mailer,
      'subject' => $details['subject'] ?? '(no subject)',
      'bcc_flag' => $details['bcc'] ?? false,
    ]);

    $event = Event::findOrFail($details['event'] ?? 0);
    $region = TeamRegion::find($details['region']);

    if (!$region || ! $event->regions()->whereKey($region->id)->exists()) {
      Log::warning('[sendToRegion] ❌ Region not found', ['region_id' => $details['region']]);
      abort(404, 'Region does not belong to this event.');
    }
    $teams = $this->teamsForEmailScope($event, $region->id);

    Log::info('[sendToRegion] 🟢 Region loaded', [
      'region_id' => $region->id,
      'region_name' => $region->region_name ?? null,
      'teams_count' => $teams->count(),
    ]);

    // ✅ Collect all player emails from all teams in the region
    $recipients = [];
    $playerCount = 0;
    $missingEmail = 0;

    foreach ($teams as $teamIndex => $team) {
      $players = $team->players ?? collect();

      Log::debug('[sendToRegion] 🔹 Processing team', [
        'team_index' => $teamIndex + 1,
        'team_id' => $team->id,
        'team_name' => $team->name ?? null,
        'players_count' => $players->count(),
      ]);

      foreach ($players as $player) {
        $playerCount++;

        if (!empty($player->email)) {
          $email = trim(strtolower((string) $player->email));
          $recipients[] = $email;

          Log::debug('[sendToRegion] 📧 Collected email', [
            'player_id' => $player->id,
            'player_name' => "{$player->name} {$player->surname}",
            'email' => $email,
          ]);
        } else {
          $recipients[] = ['email' => '', 'name' => $player->full_name];
          $missingEmail++;
          Log::warning('[sendToRegion] ⚠️ Player missing email', [
            'player_id' => $player->id,
            'player_name' => "{$player->name} {$player->surname}",
          ]);
        }
      }
    }

    // ✅ Use BulkMailDispatcher for throttled sending (prevents Exim 10-email limit)
    $recipients = array_values($recipients);
    $recipientCount = count($recipients);
    $stats = $this->composeDispatch('region_email', $region, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);

    Log::info('[sendToRegion] ✅ FINISHED', [
      'region_id' => $region->id,
      'total_players' => $playerCount,
      'queued' => $queuedCount,
      'missing_email' => $missingEmail,
    ]);

    return $this->dispatchResult();
  }

  /** ✅ All players in category */
  public function sendToAllPlayersInCategory(array $details, string $mailer)
  {
    $categoryEventId = $details['categoryEvent']
      ?? $details['catEvent']
      ?? $details['category_event_id']
      ?? null;

    \Log::info('[Mail] sendToAllPlayersInCategory called', [
      'category_event_id' => $categoryEventId,
      'event_id' => $details['event_id'] ?? null,
      'user_id' => auth()->id(),
      'keys' => array_keys($details),
    ]);

    if (!$categoryEventId) {
      return [
        'title' => 'error',
        'message' => 'Missing category_event_id.',
        'total' => 0,
        'recipients' => [],
      ];
    }

    $category = \App\Models\CategoryEvent::query()
      ->with([
        'categoryEventRegistrations.registration.players:id,email,name,surname',
      ])
      ->find($categoryEventId);

    if ($category && (int) $category->event_id !== (int) ($details['event'] ?? 0)) abort(404);
    if (!$category) {
      \Log::warning('[Mail] CategoryEvent not found', ['category_event_id' => $categoryEventId]);
      return [
        'title' => 'error',
        'message' => 'Category not found.',
        'total' => 0,
        'recipients' => [],
      ];
    }

    // Build unique recipient list from actual category registrations
    $recipients = [];

    foreach ($category->categoryEventRegistrations as $cer) {
      $players = optional($cer->registration)->players ?? collect();

      foreach ($players as $p) {
        $email = trim(strtolower((string) $p->email));
        $recipients[] = ['email' => $email, 'name' => $p->full_name];
      }
    }

    $recipients = array_values($recipients); // Convert to indexed array
    $total = count($recipients);

    \Log::info('[Mail] Category recipients resolved', [
      'category_event_id' => $categoryEventId,
      'total' => $total,
      'sample' => array_slice($recipients, 0, 5),
    ]);

    // Use BulkMailDispatcher for throttled sending
    $stats = $this->composeDispatch('category_email', $category, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);

    return $this->dispatchResult();
  }


  /** ✅ All players across all events in a series */
  public function sendToSeriesPlayers(Request $request, Series $series)
  {
    $this->dispatchStats = array_fill_keys(array_keys($this->dispatchStats), 0);
    $this->reportUrl = null;
    $this->reportUrls = [];
    // Authorize: user must be admin for at least one event in the series
    $eventIds = $series->events()->pluck('id')->toArray();
    if (empty($eventIds)) {
      abort(403);
    }

    $authorized = false;
    foreach ($eventIds as $eventId) {
      $event = Event::find($eventId);
      if ($event && auth()->user()->can('event-email.send', $event)) {
        $authorized = true;
        break;
      }
    }
    if (!$authorized) {
      abort(403);
    }

    $mailer = app(MailAccountManager::class)->getMailer();

    $request->validate([
      'campaign_key' => 'nullable|uuid',
      'emailSubject' => 'required|string|max:255',
      'message' => 'required|string',
    ]);

    Log::info('[sendToSeriesPlayers] ▶️ START', [
      'series_id' => $series->id,
      'series_name' => $series->name,
      'mailer' => $mailer,
      'user_id' => auth()->id(),
    ]);

    $details = [
      'campaign_key' => $request->campaign_key ?? (string) \Illuminate\Support\Str::uuid(),
      'fromName' => trim($request->fromName ?? 'Cape Tennis Admin'),
      'fromEmail' => match ($mailer) {
        'noreply1' => 'noreply1@capetennis.co.za',
        'noreply2' => 'noreply2@capetennis.co.za',
        default => 'noreply@capetennis.co.za',
      },
      'replyTo' => filter_var($request->replyTo, FILTER_VALIDATE_EMAIL)
        ? $request->replyTo
        : (auth()->user()->email ?? 'info@capetennis.co.za'),
      'message' => $request->message,
      'subject' => $request->emailSubject,
    ];

    // Collect unique emails across all events in the series
    $events = $series->events()->with('registrations.players')->get();
    foreach ($events as $seriesEvent) $this->authorize('event-email.send', $seriesEvent);
    $recipients = [];

    foreach ($events as $event) {
      $eventRecipients = [];
      foreach ($event->registrations as $registration) {
        foreach ($registration->players ?? collect() as $player) {
          $email = trim(strtolower((string) $player->email));
          $eventRecipients[] = ['email' => $email, 'name' => $player->full_name];
        }
      }
      $this->composeDispatch('series_email', $series, $eventRecipients, [...$details, 'event' => $event->id]);
    }

    $queuedCount = $this->dispatchStats['queued'];

    // Use BulkMailDispatcher for throttled sending


    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);

    Log::info('[sendToSeriesPlayers] ✅ FINISHED', [
      'series_id' => $series->id,
      'total_unique_players' => $queuedCount,
    ]);

    return response()->json([
      'success' => $this->dispatchStats['queued'] > 0,
      ...$this->dispatchResult(),
    ]);
  }

  /** ✅ Helper: queue the job safely */
  protected function queueMail(array $details, string $mailer = 'smtp')
  {
    $event = ! empty($details['event']) ? Event::findOrFail($details['event']) : null;
    $stats = $this->composeDispatch('generic_bulk_email', $event, [$details['email'] ?? ''], $details);
    return $stats['queued'] > 0;
  }

  /** ✅ Admin copy */
  public function sendToOwner(array $details, string $mailer, string $settingKey = null)
  {
    // Honour the email notification toggle when a specific key is provided
    if ($settingKey !== null && SiteSetting::get($settingKey, '1') !== '1') {
      return;
    }

    $adminEmail = SiteSetting::get('admin_notification_email', 'support@capetennis.co.za');
    $details['recipient_kind'] = 'admin_copy';
    $details['email'] = $adminEmail ?: 'support@capetennis.co.za';
    $this->queueMail($details, $mailer);
  }

  /** ✅ Sender copy */
  public function sendToSender(array $details, string $mailer)
  {
    if (!empty($details['replyTo'])) {
      $details['recipient_kind'] = 'sender_copy';
      $details['email'] = trim(strtolower($details['replyTo']));
      $this->queueMail($details, $mailer);
    }
  }

  /** ✅ Unregistered (unpaid) players across entire event */
  public function sendToAllUnregisteredInEvent(array $details, string $mailer)
  {
    $event = Event::find($details['event']);
    if (!$event)
      return ['message' => 'Event not found', 'title' => 'error'];

    // Collect unpaid player emails
    $recipients = [];
    foreach ($this->teamsForEmailScope($event) as $team) {
      foreach ($team->players as $player) {
        if ($player->pivot->pay_status == 0) {
          $recipients[] = trim(strtolower((string) $player->email));
        }
      }
    }
    $recipients = array_values($recipients);
    $count = count($recipients);

    // Use BulkMailDispatcher for throttled sending
    $stats = $this->composeDispatch('unregistered_event_email', $event, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return $this->dispatchResult();
  }

  /** ✅ Unregistered (unpaid) players in specific region */
  public function sendToUnregisteredInRegion(array $details, string $mailer)
  {
    $event = Event::findOrFail($details['event'] ?? 0);
    $region = TeamRegion::find($details['region']);
    if (!$region || ! $event->regions()->whereKey($region->id)->exists())
      abort(404, 'Region does not belong to this event.');
    $teams = $this->teamsForEmailScope($event, $region->id);

    // Collect unpaid player emails
    $recipients = [];
    foreach ($teams as $team) {
      foreach ($team->players as $player) {
        if ($player->pivot->pay_status == 0) {
          $recipients[] = trim(strtolower((string) $player->email));
        }
      }
    }

    $recipients = array_values($recipients);
    $count = count($recipients);

    // Use BulkMailDispatcher for throttled sending
    $stats = $this->composeDispatch('unregistered_region_email', $region, $recipients, $details);
    $queuedCount = $stats['queued'];

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return $this->dispatchResult();
  }

  /**
   * Resolve only teams that have an explicit relationship with this event.
   * Region membership alone is not sufficient because regions and legacy
   * teams are shared across historical events.
   */
  private function teamsForEmailScope(Event $event, ?int $regionId = null)
  {
    $selectionTeamIds = TeamSelectionInvitation::query()
      ->where('event_id', $event->id)
      ->pluck('team_id');

    return Team::query()->withoutGlobalScopes()
      ->with('players')
      ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
      ->where(function ($query) use ($event, $selectionTeamIds): void {
        $query->whereHas('category', fn ($category) => $category->where('event_id', $event->id));
        if ($selectionTeamIds->isNotEmpty()) {
          $query->orWhereIn('id', $selectionTeamIds);
        }
      })
      ->get();
  }

  /** ✅ AJAX helpers */
  public function getPlayers($eventId)
  {
    $event = Event::findOrFail($eventId);
    $this->authorize('event-email.view', $event);

    try {
      $event = Event::with('registrations.players')->findOrFail($eventId);

      $players = collect();
      if ($event->registrations->isNotEmpty()) {
        $players = $event->registrations->flatMap(fn($r) => $r->players);
      } else {
        $players = $this->teamsForEmailScope($event)->flatMap(fn($team) => $team->players);
      }

      $data = $players->filter(fn($p) => $p && $p->email)
        ->unique('id')
        ->map(fn($p) => ['id' => $p->id, 'text' => "{$p->name} {$p->surname}", 'email' => $p->email])
        ->values();

      return response()->json($data);
    } catch (\Throwable $e) {
      Log::error('Email getPlayers failed', ['event_id' => $eventId, 'error' => $e->getMessage()]);
      return response()->json(['error' => 'Failed to load players.'], 500);
    }
  }

  public function getTeams($eventId)
  {
    $event = Event::findOrFail($eventId);
    $this->authorize('event-email.view', $event);

    $teams = $this->teamsForEmailScope($event)
      ->unique('id')
      ->map(fn($t) => [
        'id' => $t->id,
        'text' => "{$t->name} (" . ($t->regions->region_name ?? 'No Region') . ")",
      ])
      ->values();

    return response()->json($teams);
  }

  public function getRegions($eventId)
  {
    $event = Event::with('regions')->findOrFail($eventId);
    $this->authorize('event-email.view', $event);

    $regions = $event->regions
      ->map(fn($r) => [
        'id' => $r->id,
        'text' => $r->region_name,
      ])
      ->values();

    return response()->json($regions);
  }



}
