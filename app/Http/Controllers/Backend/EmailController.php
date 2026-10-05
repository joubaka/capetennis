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
    $request->validate(['event_id'=>'required|integer|exists:events,id','campaign_key'=>'nullable|uuid','emailSubject'=>'required|string|max:200','message'=>'required|string|max:30000']);
    $event = Event::findOrFail($request->event_id);
    $this->authorize('event-email.bulk-send',$event);
    if (is_numeric($request->to)) {
      $player = Player::whereKey($request->to)->where(function ($query) use ($event) {
        $query->whereHas('registrations.categoryEventRegistrations.categoryEvent', fn ($q) => $q->where('event_id', $event->id))
          ->orWhereHas('teams.category', fn ($q) => $q->where('event_id', $event->id))
          ->orWhereIn('id', EventNomination::where('event_id', $event->id)->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->whereNotNull('player_id')->select('player_id'))
          ->orWhereIn('id', TeamSelectionInvitation::where('event_id', $event->id)->whereHas('selectionImport', fn ($q) => $q->where('event_id', $event->id))->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->select('player_id'))
          ->orWhereIn('id', \App\Models\InterprovincialTrialInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->select('player_id'))
          ->orWhereIn('id', \App\Models\MastersInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->select('player_id'));
      })->first();

      abort_unless($player,404,'Invalid player selected.');
    }

    return $this->reviewEventResponse($request,$event);
  }

  private function reviewEventResponse(Request $request, Event $event)
  {
    abort_unless(app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event,$request->user()),403);
    $options = ['scope'=>'all', 'recipients'=>'players'];
    if ($request->target_type === 'player' || is_numeric($request->to) || filter_var($request->to, FILTER_VALIDATE_EMAIL)) {
      $options = is_numeric($request->to) ? ['scope'=>'individual', 'individual_key'=>'player:'.$request->to, 'recipients'=>'players'] : ['scope'=>'direct', 'direct_email'=>$request->to, 'recipients'=>'players'];
      if ($options['scope']==='direct') $request->validate(['to'=>'required|email|max:255']);
    } elseif ($request->target_type === 'team') {
      $options = ['scope'=>'team', 'team_id'=>$request->team_id, 'recipients'=>'players'];
    } elseif ($request->target_type === 'region') {
      $options = ['scope'=>'region', 'region_id'=>$request->region_id, 'recipients'=>'players'];
    } elseif ($request->filled('category_event_id')) {
      $options = ['scope'=>'legacy_registered', 'category_event_id'=>$request->category_event_id, 'recipients'=>'players'];
    }
    $target = mb_strtolower(trim((string) $request->to));
    if (! is_numeric($request->to) && ! filter_var($request->to, FILTER_VALIDATE_EMAIL) && $request->target_type !== 'player') {
      $groups = [
        'all players in event'=>'event', 'all players in region'=>'region', 'all players in category'=>'category',
        'all players in team'=>'team', 'all players in nominations'=>'nominations', 'all nominated players'=>'nominations',
        'all unregistered players in event'=>'event', 'all unregistered players in region'=>'region', 'all unregistered players in team'=>'team',
      ];
      $group = $groups[$target] ?? (in_array($request->target_type,['team','region'],true) ? $request->target_type : null);
      abort_unless($group, 422, 'Select a supported recipient group.');
      $options = match ($group) {
        'region'=>['scope'=>'region', 'region_id'=>$request->region_id ?? $request->region, 'recipients'=>'players'],
        'team'=>['scope'=>'team', 'team_id'=>$request->team_id, 'recipients'=>'players'],
        'category'=>['scope'=>'legacy_registered', 'category_event_id'=>$request->categoryEvent ?? $request->category_event_id ?? $request->catEvent, 'recipients'=>'players'],
        'nominations'=>['scope'=>'nominations', 'recipients'=>'players'],
        default=>['scope'=>!$event->isTeam() && !$event->isInterprovincialTrials() && !str_contains($target,'unregistered') ? 'legacy_registered' : 'all', 'recipients'=>'players'],
      };
      $options['filter'] = str_contains($target,'unregistered') ? 'not_registered' : 'all';
    }
    $options['filter'] ??= 'all';
    if ($options['scope']==='team') Team::withoutGlobalScopes()->whereHas('category',fn($q)=>$q->where('event_id',$event->id))->findOrFail($options['team_id']);
    if ($options['scope']==='region') abort_unless($event->regions()->where('team_regions.id',$options['region_id'])->exists(),404);
    if ($options['scope']==='legacy_registered' && isset($options['category_event_id'])) $event->categoryEvents()->findOrFail($options['category_event_id']);
    $request->session()->flash('compose_options',$options);
    $request->session()->flash('compose_subject',$request->emailSubject);
    $request->session()->flash('compose_body',trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?\s*>/i',"\n",$request->message))));
    $url = route('backend.event-communications.index',['event'=>$event,'compose'=>1]);

    $notice = 'No emails queued. Communications uses your account name and email for the sender name and reply-to address. Prior BCC choices are not carried over. Review the exact recipients and message before approving.';
    $request->session()->flash('info', $notice);

    if ($request->expectsJson()) return response()->json(['success'=>true,'review_required'=>true,'review_url'=>$url,'result'=>['title'=>'info','report_url'=>$url,'message'=>$notice]]);
    return redirect($url)->with('info', $notice);
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
    $data = $request->validate([
      'campaign_key'=>'required|uuid','emailSubject'=>'required|string|max:200','message'=>'required|string|max:30000',
      'fromName'=>'nullable|string|max:100','replyTo'=>'nullable|email|max:255',
    ]);
    app(\App\Services\SeriesCommunicationService::class)->preview($series,$request->user(),$data['campaign_key'],$data['emailSubject'],$data['message'],trim($data['fromName'] ?? $request->user()->name),$data['replyTo'] ?? $request->user()->email);
    $reviewUrl = route('series.email.review',['series'=>$series,'intent'=>$data['campaign_key']]);

    return response()->json(['success'=>true,'review_required'=>true,'review_url'=>$reviewUrl,'message'=>'No emails queued. Review every event recipient and exact message before approving this intent.']);
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
