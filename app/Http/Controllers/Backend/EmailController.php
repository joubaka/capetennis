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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\SiteSetting;


class EmailController extends Controller
{

  public function sendEmail(Request $request)
  {
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

      $player = Player::find($recipient);

      if (!$player) {
        Log::warning('[Mail] Player not found', ['player_id' => $recipient]);
        return response()->json([
          'success' => false,
          'message' => 'Invalid player selected.'
        ], 422);
      }

      if (empty($player->email)) {
        Log::warning('[Mail] Player has no email', [
          'player_id' => $player->id,
          'name' => "{$player->name} {$player->surname}",
        ]);
        return response()->json([
          'success' => false,
          'message' => 'Player has no email address.'
        ], 422);
      }

      $details['email'] = trim(strtolower($player->email));
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
        'success' => true,
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
        'success' => true,
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
      'success' => true,
      'mailer' => $mailer,
      'result' => $result,
    ]);
  }

  /**
   * ✅ Unified event-type handler (individual / team)
   */
  protected function sendToEventType(array $details, string $mailer)
  {  
    $event = Event::with('eventType', 'region_in_events')->find($details['event']);
    if (!$event)
      return ['message' => 'Event not found', 'title' => 'error'];

    if ($event->eventType->type == 1) {

      return $this->sendToEvent($details, $mailer);
    } elseif ($event->eventType->type == 2) {
      foreach ($event->region_in_events as $region) {
        $details['region'] = $region->id;
        $this->sendToRegion($details, $mailer);
      }
      return ['message' => 'Emails sent to all regions', 'title' => 'success'];
    }

    return ['message' => 'Unsupported event type', 'title' => 'error'];
  }

  /** ✅ Individual player */
  public function sendToIndividual(array $details, string $mailer)
  {
    if (empty($details['email'])) {
      return ['message' => 'No valid email address provided.', 'title' => 'error'];
    }

    $this->queueMail($details, $mailer);
    $this->sendToOwner($details, $mailer);

    return ['message' => 'Email sent successfully to 1 player.', 'title' => 'success'];
  }

  /** ✅ All players registered in event */


  public function sendToEvent(array $details, string $mailer)
  {
    Log::info('[sendToEvent] ▶️ START', [
      'event_id' => $details['event'] ?? null,
      'mailer' => $mailer,
      'subject' => $details['emailSubject'] ?? '(no subject)'
    ]);

    $event = Event::with('registrations.players')->find($details['event']);

    if (!$event) {
      Log::warning('[sendToEvent] ❌ Event not found', ['event_id' => $details['event']]);
      return ['message' => 'Event not found.', 'title' => 'error'];
    }

    $playerCount = 0;
    $queuedCount = 0;
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
          $queuedCount++;
          $details['email'] = trim(strtolower($player->email));

          Log::info('[sendToEvent] 📧 Queuing email', [
            'player_id' => $player->id ?? null,
            'player_name' => "{$player->name} {$player->surname}",
            'email' => $player->email
          ]);

          try {
            $this->queueMail($details, $mailer);
          } catch (\Throwable $e) {
            Log::error('[sendToEvent] 💥 Mail queue failed', [
              'player_id' => $player->id ?? null,
              'email' => $player->email,
              'error' => $e->getMessage()
            ]);
          }
        } else {
          $missingEmail++;
          Log::warning('[sendToEvent] ⚠️ Player missing email', [
            'player_id' => $player->id ?? null,
            'player_name' => "{$player->name} {$player->surname}"
          ]);
        }
      }
    }

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

    return [
      'message' => "Emails queued for {$queuedCount} players (missing email: {$missingEmail})",
      'title' => 'success'
    ];
  }

  /** ✅ All nominations */
  public function sendToNominations(array $details, string $mailer)
  {
    $eventId = $details['event'];
    $nominations = EventNomination::where('event_id', $eventId)->with('player')->get();

    foreach ($nominations as $nom) {
      if (!empty($nom->player->email)) {
        $details['email'] = trim(strtolower($nom->player->email));
        $this->queueMail($details, $mailer);
      }
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return ['message' => 'Emails sent to all nominations.', 'title' => 'success'];
  }

  /** ✅ Unpaid players in team */
  public function sendToEventUnregisteredTeam(array $details, string $mailer)
  {
    $region = TeamRegion::with('teams.players')->find($details['region']);
    if (!$region)
      return ['message' => 'Region not found', 'title' => 'error'];

    $count = 0;
    foreach ($region->teams as $team) {
      foreach ($team->players as $p) {
        if ($p->pivot->pay_status == 0 && !empty($p->email)) {
          $details['email'] = trim(strtolower($p->email));
          $this->queueMail($details, $mailer);
          $count++;
        }
      }
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return ['message' => "$count unpaid players emailed.", 'title' => 'success'];
  }

  /** ✅ All players in team */
  public function sendToTeam(array $details, string $mailer)
  {
    $team = Team::with('players')->find($details['team']);
    if (!$team)
      return ['message' => 'Team not found.', 'title' => 'error'];

    foreach ($team->players as $player) {
      if (!empty($player->email)) {
        $details['email'] = trim(strtolower($player->email));
        $this->queueMail($details, $mailer);
      }
    }

    $this->sendToOwner($details, $mailer);
    return ['message' => 'Emails sent to all players in team.', 'title' => 'success'];
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

    $region = TeamRegion::with('teams.players')->find($details['region']);
    
    if (!$region) {
      Log::warning('[sendToRegion] ❌ Region not found', ['region_id' => $details['region']]);
      return ['message' => 'Region not found.', 'title' => 'error'];
    }

    Log::info('[sendToRegion] 🟢 Region loaded', [
      'region_id' => $region->id,
      'region_name' => $region->region_name ?? null,
      'teams_count' => $region->teams->count(),
    ]);

    $playerCount = 0;
    $queuedCount = 0;
    $missingEmail = 0;

    foreach ($region->teams as $teamIndex => $team) {
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
          $queuedCount++;
          $details['email'] = trim(strtolower($player->email));

          Log::debug('[sendToRegion] 📧 Queuing email', [
            'player_id' => $player->id,
            'player_name' => "{$player->name} {$player->surname}",
            'email' => $details['email'],
          ]);

          $this->queueMail($details, $mailer);
        } else {
          $missingEmail++;
          Log::warning('[sendToRegion] ⚠️ Player missing email', [
            'player_id' => $player->id,
            'player_name' => "{$player->name} {$player->surname}",
          ]);
        }
      }
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);

    Log::info('[sendToRegion] ✅ FINISHED', [
      'region_id' => $region->id,
      'total_players' => $playerCount,
      'queued' => $queuedCount,
      'missing_email' => $missingEmail,
    ]);

    return [
      'message' => "Emails queued for {$queuedCount} players in region (missing email: {$missingEmail})",
      'title' => 'success'
    ];
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
        if ($email !== '') {
          $recipients[$email] = [
            'email' => $email,
            'name' => trim(($p->name ?? '') . ' ' . ($p->surname ?? '')),
            'registration_id' => $cer->registration_id,
            'category_event_registration_id' => $cer->id,
          ];
        }
      }
    }

    $recipients = array_values($recipients);        // keyed-by-email -> list
    $total = count($recipients);

    \Log::info('[Mail] Category recipients resolved', [
      'category_event_id' => $categoryEventId,
      'total' => $total,
      'emails' => array_column($recipients, 'email'),
    ]);

    // Queue mail
    foreach ($recipients as $r) {
      $details['email'] = $r['email'];
      $this->queueMail($details, $mailer);
    }

    $this->sendToOwner($details, $mailer);

    return [
      'title' => 'success',
      'message' => "Emails queued to {$total} unique recipients.",
      'total' => $total,
      'recipients' => $recipients, // includes email + name + ids
    ];
  }


  /** ✅ All players across all events in a series */
  public function sendToSeriesPlayers(Request $request, Series $series)
  {
    $mailer = app(MailAccountManager::class)->getMailer();

    $request->validate([
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
    $recipients = collect();

    foreach ($events as $event) {
      foreach ($event->registrations as $registration) {
        foreach ($registration->players ?? collect() as $player) {
          $email = trim(strtolower((string) $player->email));
          if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[$email] = $player;
          }
        }
      }
    }

    $queuedCount = 0;
    foreach ($recipients as $email => $player) {
      $details['email'] = $email;
      $this->queueMail($details, $mailer);
      $queuedCount++;
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);

    Log::info('[sendToSeriesPlayers] ✅ FINISHED', [
      'series_id' => $series->id,
      'total_unique_players' => $queuedCount,
    ]);

    return response()->json([
      'success' => true,
      'title' => 'success',
      'message' => "Emails queued for {$queuedCount} unique players across {$events->count()} events in series.",
    ]);
  }

  /** ✅ Helper: queue the job safely */
  protected function queueMail(array $details, string $mailer = 'smtp')
  {
    $email = trim(strtolower($details['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      Log::warning('[Mail] ❌ Skipped invalid email', [
        'email' => $email,
        'subject' => $details['subject'] ?? null,
      ]);
      return false;
    }

    $details['mailer'] = $mailer;
    $details['email'] = $email;

    try {

      dispatch(new SendEmailJob($details))->onQueue('default');

      Log::info('[Mail] 📬 QUEUED', [
        'to' => $email,
        'mailer' => $mailer,
        'subject' => $details['subject'] ?? null,
        'event' => $details['event'] ?? null,
        'team' => $details['team'] ?? null,
        'region' => $details['region'] ?? null,
      ]);

      return true;

    } catch (\Throwable $e) {

      Log::error('[Mail] 💥 QUEUE FAILED', [
        'to' => $email,
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /** ✅ Admin copy */
  public function sendToOwner(array $details, string $mailer, string $settingKey = null)
  {
    // Honour the email notification toggle when a specific key is provided
    if ($settingKey !== null && SiteSetting::get($settingKey, '1') !== '1') {
      return;
    }

    $adminEmail = SiteSetting::get('admin_notification_email', 'support@capetennis.co.za');
    $details['email'] = $adminEmail ?: 'support@capetennis.co.za';
    $this->queueMail($details, $mailer);
  }

  /** ✅ Sender copy */
  public function sendToSender(array $details, string $mailer)
  {
    if (!empty($details['replyTo'])) {
      $details['email'] = trim(strtolower($details['replyTo']));
      $this->queueMail($details, $mailer);
    }
  }

  /** ✅ Unregistered (unpaid) players across entire event */
  public function sendToAllUnregisteredInEvent(array $details, string $mailer)
  {
    $event = Event::with('region_in_events.teams.players')->find($details['event']);
    if (!$event)
      return ['message' => 'Event not found', 'title' => 'error'];

    $count = 0;
    foreach ($event->region_in_events as $region) {
      foreach ($region->teams as $team) {
        foreach ($team->players as $player) {
          if ($player->pivot->pay_status == 0 && !empty($player->email)) {
            $details['email'] = trim(strtolower($player->email));
            $this->queueMail($details, $mailer);
            $count++;
          }
        }
      }
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return ['message' => "$count unregistered players emailed across entire event.", 'title' => 'success'];
  }

  /** ✅ Unregistered (unpaid) players in specific region */
  public function sendToUnregisteredInRegion(array $details, string $mailer)
  {
    $region = TeamRegion::with('teams.players')->find($details['region']);
    if (!$region)
      return ['message' => 'Region not found', 'title' => 'error'];

    $count = 0;
    foreach ($region->teams as $team) {
      foreach ($team->players as $player) {
        if ($player->pivot->pay_status == 0 && !empty($player->email)) {
          $details['email'] = trim(strtolower($player->email));
          $this->queueMail($details, $mailer);
          $count++;
        }
      }
    }

    $this->sendToOwner($details, $mailer);
    $this->sendToSender($details, $mailer);
    return ['message' => "$count unregistered players emailed in region: {$region->region_name}.", 'title' => 'success'];
  }

  /** ✅ AJAX helpers */
  public function getPlayers($eventId)
  {
    try {
      $event = Event::with(['registrations.players', 'region_in_events.teams.players'])->findOrFail($eventId);

      $players = collect();
      if ($event->registrations->isNotEmpty()) {
        $players = $event->registrations->flatMap(fn($r) => $r->players);
      } elseif ($event->region_in_events->isNotEmpty()) {
        $players = $event->region_in_events
          ->flatMap(fn($region) => $region->teams)
          ->flatMap(fn($team) => $team->players);
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
    $event = Event::with('region_in_events.teams.regions')->findOrFail($eventId);

    $teams = $event->region_in_events
      ->flatMap(fn($region) => $region->teams)
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

    $regions = $event->regions
      ->map(fn($r) => [
        'id' => $r->id,
        'text' => $r->region_name,
      ])
      ->values();

    return response()->json($regions);
  }



}
