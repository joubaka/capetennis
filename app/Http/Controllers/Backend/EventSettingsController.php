<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\EventConvenor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EventSettingsController extends Controller
{
  /**
   * Display the event settings page
   */
  public function index(Event $event)
  {
    Gate::authorize('event.settings.manage', $event);

    $assignments = EventConvenor::where('event_id', $event->id)
      ->with('user.roles')
      ->get();
    $scoringAccounts = $assignments->filter(
      fn (EventConvenor $assignment) => $assignment->role === 'score-keeper'
    );
    $convenors = $assignments->reject(
      fn (EventConvenor $assignment) => $scoringAccounts->contains('id', $assignment->id)
    );
    $selectedUserIds = $event->admins()->pluck('users.id')
      ->merge($assignments->pluck('user_id'))
      ->unique();
    $users = User::with('roles')
      ->whereIn('id', $selectedUserIds)
      ->orderBy('name')
      ->get();
    $scoringAccountIds = $scoringAccounts->pluck('user_id');
    $scoringUsers = $users->whereIn('id', $scoringAccountIds);

    return view('backend.event.settings', [
      'event' => $event,
      'users' => $users,
      'convenors' => $convenors,
      'scoringAccounts' => $scoringAccounts,
      'scoringUsers' => $scoringUsers,
    ]);
  }

  /**
   * Update event settings (AJAX, partial-safe)
   */
  public function update(Request $request, Event $event)
  {
    Gate::authorize('event.settings.manage', $event);

    Log::info('🛠 Event settings update START', [
      'event_id' => $event->id,
      'payload' => $request->all(),
      'user_id' => auth()->id(),
    ]);

    // ✅ PARTIAL-SAFE VALIDATION
    $data = $request->validate([
      'name' => 'sometimes|required|string|max:255',
      'status' => 'sometimes|nullable|string',
      'start_date' => 'sometimes|nullable|date',
      'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
      'information' => 'sometimes|nullable|string',
      'venue_notes' => 'sometimes|nullable|string',
      'entryFee' => 'sometimes|nullable|integer',
      'deadline' => 'sometimes|nullable|integer',
      'withdrawal_deadline' => 'sometimes|nullable|date',
      'eventType' => 'sometimes|required|integer',
      'email' => 'sometimes|nullable|email',
      'published' => 'sometimes|boolean',
      'signUp' => 'sometimes|boolean',
      'organizer' => 'sometimes|nullable|string|max:191',

      'logo_existing' => [
        'sometimes',
        'nullable',
        'string',
        'max:255',
        function (string $attribute, mixed $value, \Closure $fail): void {
          if ($value !== null && ! is_file(public_path('assets/img/logos/'.basename($value)))) {
            $fail('The selected event logo is no longer available.');
          }
        },
      ],
      'logo_upload' => 'sometimes|image|max:2048',

      'admins' => 'sometimes|array',
      'admins.*' => 'integer|exists:users,id',
      'convenors' => 'sometimes|array',
      'convenors.*' => 'integer|exists:users,id',
      'convenor_starts_at' => 'sometimes|nullable|date',
      'convenor_expires_at' => 'sometimes|nullable|date|after:convenor_starts_at',
      'scoring_accounts' => 'sometimes|array',
      'scoring_accounts.*' => [
        'integer',
        'distinct',
        'exists:users,id',
        function (string $attribute, mixed $value, \Closure $fail): void {
          $account = User::with('roles')->find($value);
          if ($account?->hasAnyRole(['super-user', 'admin'])) {
            $fail('Scoring access must be assigned to a dedicated non-administrator account.');
          }
        },
      ],
      'scoring_starts_at' => 'sometimes|nullable|date',
      'scoring_expires_at' => 'sometimes|nullable|date|after:scoring_starts_at',
    ]);

    $scoringIds = collect($data['scoring_accounts'] ?? [])->map(fn ($id) => (int) $id);
    $managementIds = collect($data['admins'] ?? [])
      ->merge($data['convenors'] ?? [])
      ->map(fn ($id) => (int) $id);
    if ($scoringIds->intersect($managementIds)->isNotEmpty()) {
      throw ValidationException::withMessages([
        'scoring_accounts' => 'Use separate accounts for scoring and event management.',
      ]);
    }

    Log::debug('📥 Validated data', $data);

    /**
     * LOGO HANDLING
     */
    if ($request->hasFile('logo_upload')) {
      $file = $request->file('logo_upload');

      $filename = Str::slug(
        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
      ) . '.' . $file->getClientOriginalExtension();

      $file->move(public_path('assets/img/logos'), $filename);
      $event->logo = $filename;

      Log::info('🖼 Logo uploaded', ['logo' => $filename]);
    } elseif ($request->has('logo_existing')) {
      $event->logo = empty($data['logo_existing']) ? null : basename($data['logo_existing']);
    }

    // Event directors and scoring accounts share the existing event-scoped
    // assignment table. The row role keeps dedicated scorers out of the
    // operational/finance director list while the global role admits them to
    // the scorer routes.
    if ($request->hasAny([
      'convenors', 'convenor_starts_at', 'convenor_expires_at',
      'scoring_accounts', 'scoring_starts_at', 'scoring_expires_at',
    ])) {
      Log::info('👥 Syncing convenors', [
        'event_id' => $event->id,
        'convenors' => $request->input('convenors'),
        'scoring_accounts' => $request->input('scoring_accounts'),
      ]);

      $directorStartsAt = $request->input('convenor_starts_at') ?: $event->start_date?->format('Y-m-d H:i:s');
      $directorExpiresAt = $request->input('convenor_expires_at') ?: $event->end_date?->format('Y-m-d H:i:s');
      $scoringStartsAt = $request->input('scoring_starts_at') ?: $event->start_date?->format('Y-m-d H:i:s');
      $scoringExpiresAt = $request->input('scoring_expires_at') ?: $event->end_date?->format('Y-m-d H:i:s');

      DB::transaction(function () use (
        $request, $event, $directorStartsAt, $directorExpiresAt,
        $scoringStartsAt, $scoringExpiresAt
      ): void {
        $removedScoringIds = collect();

        if ($request->has('scoring_accounts')) {
          Role::firstOrCreate(['name' => 'score-keeper', 'guard_name' => 'web']);
          $scoringIds = collect($request->input('scoring_accounts', []))->map(fn ($id) => (int) $id)->unique();
          $previousScoringIds = EventConvenor::where('event_id', $event->id)
            ->where('role', 'score-keeper')
            ->pluck('user_id');
          $removedScoringIds = $previousScoringIds->diff($scoringIds);

          // Remove old scoring assignments first. If an account is being moved
          // to Event directors, the director sync below recreates it safely.
          EventConvenor::where('event_id', $event->id)
            ->whereIn('user_id', $removedScoringIds)
            ->delete();
        }

        if ($request->has('convenors')) {
          $directorIds = collect($request->input('convenors', []))->map(fn ($id) => (int) $id)->unique();
          EventConvenor::where('event_id', $event->id)
            ->where('role', '!=', 'score-keeper')
            ->whereNotIn('user_id', $directorIds)
            ->delete();

          foreach ($directorIds as $userId) {
            EventConvenor::updateOrCreate(
              ['event_id' => $event->id, 'user_id' => $userId],
              ['role' => 'hoof', 'starts_at' => $directorStartsAt, 'expires_at' => $directorExpiresAt]
            );
          }
        } elseif ($request->has('convenor_starts_at') || $request->has('convenor_expires_at')) {
          EventConvenor::where('event_id', $event->id)
            ->where('role', '!=', 'score-keeper')
            ->update(['starts_at' => $directorStartsAt, 'expires_at' => $directorExpiresAt]);
        }

        if ($request->has('scoring_accounts')) {
          EventConvenor::where('event_id', $event->id)
            ->where('role', 'score-keeper')
            ->whereNotIn('user_id', $scoringIds)
            ->delete();

          foreach (User::whereIn('id', $scoringIds)->get() as $account) {
            $account->assignRole('score-keeper');
            EventConvenor::updateOrCreate(
              ['event_id' => $event->id, 'user_id' => $account->id],
              ['role' => 'score-keeper', 'starts_at' => $scoringStartsAt, 'expires_at' => $scoringExpiresAt]
            );
          }

          foreach (User::whereIn('id', $removedScoringIds)->get() as $account) {
            if (! EventConvenor::where('user_id', $account->id)->where('role', 'score-keeper')->exists()) {
              $account->removeRole('score-keeper');
            }
          }
        } elseif ($request->has('scoring_starts_at') || $request->has('scoring_expires_at')) {
          EventConvenor::where('event_id', $event->id)
            ->where('role', 'score-keeper')
            ->update(['starts_at' => $scoringStartsAt, 'expires_at' => $scoringExpiresAt]);
        }
      });
    }

    /**
     * UPDATE EVENT FIELDS
     */
    $updateData = collect($data)
      ->except([
        'admins', 'convenors', 'convenor_starts_at', 'convenor_expires_at',
        'scoring_accounts', 'scoring_starts_at', 'scoring_expires_at',
        'logo_upload', 'logo_existing',
      ])
      ->toArray();

    // Boolean safety
    if ($request->has('published')) {
      $updateData['published'] = $request->boolean('published');
    }
    if ($request->has('signUp')) {
      $updateData['signUp'] = $request->boolean('signUp');
    }

    Log::debug('🔁 Mapped data', $updateData);

    $event->update($updateData);

    // Masters registration is displayed on the public event page from the
    // invitation batch, while this settings page controls the event signup
    // switch. Keep the two gates synchronized so an organiser cannot see
    // "Signup open" here while Masters registration remains closed publicly.
    if ($request->has('signUp') && $event->isMasters()) {
      $mastersBatch = \App\Models\MastersInvitationBatch::where('event_id', $event->id)
        ->latest('id')
        ->first();

      if ($mastersBatch) {
        $mastersBatch->update([
          'registration_open' => $request->boolean('signUp')
            && $mastersBatch->status === 'sent'
            && (bool) $mastersBatch->public_list_published,
        ]);
      }
    }

    /**
     * ADMINS
     */
    if ($request->has('admins')) {
      Log::info('👥 Syncing admins', [
        'event_id' => $event->id,
        'admins' => $request->input('admins'),
      ]);

      $event->admins()->sync($request->input('admins', []));
    }

    Log::info('✅ Event settings update COMPLETE', [
      'event_id' => $event->id,
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Event settings saved.',
      'saved_at' => now()->toIso8601String(),
    ]);
  }

  /**
   * Search assignable users without exposing the full user directory in HTML.
   */
  public function searchUsers(Request $request, Event $event)
  {
    Gate::authorize('event.settings.manage', $event);

    $data = $request->validate([
      'q' => 'sometimes|nullable|string|max:100',
      'scope' => 'sometimes|in:management,scoring',
    ]);
    $query = trim($data['q'] ?? '');

    $users = User::query()
      ->with('roles:id,name')
      ->when(($data['scope'] ?? 'management') === 'scoring', function ($builder) {
        $builder->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['super-user', 'admin']));
      })
      ->when($query !== '', function ($builder) use ($query) {
        $builder->where(function ($userQuery) use ($query) {
          $userQuery->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%");
        });
      })
      ->orderBy('name')
      ->limit(20)
      ->get(['id', 'name', 'email']);

    return response()->json([
      'results' => $users->map(fn (User $user) => [
        'id' => $user->id,
        'text' => "{$user->name} ({$user->email})",
      ])->values(),
    ]);
  }

  /**
   * Update category fee override (AJAX)
   */
  public function updateCategoryFee(Request $request, CategoryEvent $categoryEvent)
  {
    $data = $request->validate([
      'entry_fee' => 'nullable|integer|min:0',
      'enabled' => 'nullable|boolean',
    ]);

    if (array_key_exists('enabled', $data) && !$data['enabled']) {
      $data['entry_fee'] = null;
    }

    unset($data['enabled']);

    $categoryEvent->update($data);

    return response()->json(['success' => true]);
  }
}
