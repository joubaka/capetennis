<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Audit\AuditWriter;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
  /**
   * Search users for super-user-only Select2 controls.
   */
  public function search(Request $request)
  {
    abort_unless($request->user()?->hasRole('super-user'), 403);

    $validated = $request->validate([
      'q' => ['nullable', 'string', 'max:100'],
      'page' => ['nullable', 'integer', 'min:1'],
    ]);
    $q = trim((string) ($validated['q'] ?? ''));
    $page = (int) ($validated['page'] ?? 1);

    $users = User::query()
      ->when($q !== '', function ($query) use ($q) {
        $terms = preg_split('/\s+/', $q);

        foreach ($terms as $term) {
          $term = addcslashes($term, '\\%_');
          $query->where(function ($search) use ($term) {
            $search->where('name', 'like', "%{$term}%")
              ->orWhere('userName', 'like', "%{$term}%")
              ->orWhere('userSurname', 'like', "%{$term}%");
          });
        }
      })
      ->orderBy('name')
      ->orderBy('id')
      ->simplePaginate(20, ['id', 'name', 'userName', 'userSurname'], 'page', $page);

    return response()->json([
      'results' => collect($users->items())->map(function (User $user): array {
        $profileName = trim(($user->userName ?? '').' '.($user->userSurname ?? ''));
        $displayName = $profileName !== '' ? $profileName : trim((string) $user->name);

        return [
          'id' => $user->id,
          'text' => $displayName !== '' ? $displayName : 'User #'.$user->id,
        ];
      })->values(),
      'pagination' => ['more' => $users->hasMorePages()],
    ]);
  }

  /**
   * Display the users management page.
   */
  public function index(Request $request)
  {
    // If AJAX request, return JSON for DataTables
    if ($request->ajax() || $request->wantsJson()) {
      return $this->directoryData($request);
    }

    // Otherwise return the view
    $roles = Role::all();
    return view('backend.user.index', compact('roles'));
  }

  /**
   * Get users data for DataTables (AJAX endpoint).
   */
  public function data(Request $request)
  {
    return $this->directoryData($request);
  }

  private function directoryData(Request $request)
  {
    $start = max(0, (int) $request->input('start', 0));
    $length = min(100, max(1, (int) $request->input('length', 25)));
    $term = mb_substr(trim((string) $request->input('search.value', '')), 0, 100);
    $query = User::query();
    $total = (clone $query)->count();
    if ($term !== '') {
      $query->where(function ($search) use ($term) {
        foreach (['name', 'userName', 'userSurname', 'email', 'cell_nr'] as $column) {
          $search->orWhere($column, 'like', '%'.addcslashes($term, '\\%_').'%');
        }
      });
    }
    $filtered = (clone $query)->count();
    $columns = [0 => 'id', 1 => 'name', 2 => 'email', 4 => 'created_at'];
    $column = $columns[(int) $request->input('order.0.column', 0)] ?? 'id';
    $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
    $users = $query->with('roles:id,name')->orderBy($column, $direction)->orderBy('id')
      ->offset($start)->limit($length)
      ->get(['id', 'name', 'userName', 'userSurname', 'email', 'created_at']);

    return response()->json(['draw' => max(0, (int) $request->input('draw', 0)),
      'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $users]);
  }

  /**
   * Store a newly created resource.
   * (Not used currently)
   */
  public function store(Request $request)
  {
    abort(404);
  }

  /**
   * Display the specified user's profile, wallet & transactions.
   */
  public function show(User $user)
  {
    $user->load(['wallet', 'players', 'roles']);

    $wallet       = $user->wallet;
    $transactions = $wallet ? $wallet->transactions()->latest()->orderByDesc('id')->paginate(25) : collect();
    $players      = collect(); // Player selection uses the bounded, private-safe search endpoint.

    return view('backend.user.show', compact('user', 'wallet', 'transactions', 'players'));
  }

  /**
   * Show the form for editing the specified resource.
   * (Handled via modal)
   */
  public function edit(User $user)
  {
    abort(404);
  }

  /**
   * Update the specified user (AJAX).
   */
  public function update(Request $request, User $user)
  {
    // 🔒 Allow self-edit or admin only
    if (
      auth()->id() !== $user->id &&
      !auth()->user()->can('admin')
    ) {
      abort(403);
    }

    $validated = $request->validate([
      'userName' => 'nullable|string|max:255',
      'userSurname' => 'nullable|string|max:255',
      'email' => 'nullable|email|max:255',
      'cell_nr' => 'nullable|string|max:50',
    ]);

    $user->update(array_merge($validated, [
      // Keep `name` in sync if you still use it
      'name' => trim(
        ($validated['userName'] ?? '') . ' ' .
        ($validated['userSurname'] ?? '')
      ),
    ]));

    return response()->json([
      'success' => true,
      'message' => '✅ Profile updated successfully',
      'user' => $user->fresh(),
    ]);
  }

  /**
   * Remove the specified user.
   */
  public function destroy(User $user)
  {
    DB::transaction(function () use ($user): void {
      $user->delete();
    });

    return response()->json([
      'success' => true,
      'message' => 'User deleted'
    ]);
  }

  /**
   * Remove admin role.
   */
  public function removeRole(Request $request, $id)
  {
    $request->validate(['role' => 'required|string']);
    $user = User::findOrFail($id);

    // permission check: only admin or self (adjust as needed)
    if (auth()->id() !== $user->id && !auth()->user()->can('admin')) {
        abort(403);
    }

    $roleName = $request->input('role');
    DB::transaction(function () use ($user, $roleName): void {
      $before = $user->getRoleNames()->values()->all();
      $user->removeRole($roleName);
      app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'user.role-removed',
        'subject' => $user,
        'before' => ['roles' => $before],
        'after' => ['roles' => $user->fresh()->getRoleNames()->values()->all()],
        'reason' => "Removed role {$roleName}",
      ], true);
    });

    return response()->json([
        'success' => true,
        'message' => "Role '{$roleName}' removed"
    ]);
  }

  /**
   * Add admin role.
   */
  public function addRole(Request $request, $id)
  {
    $request->validate(['role' => 'required|string']);
    $user = User::findOrFail($id);

    // permission check: only admin or self (adjust as needed)
    if (auth()->id() !== $user->id && !auth()->user()->can('admin')) {
        abort(403);
    }

    $roleName = $request->input('role');
    DB::transaction(function () use ($user, $roleName): void {
      $before = $user->getRoleNames()->values()->all();
      $user->assignRole($roleName);
      app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'user.role-assigned',
        'subject' => $user,
        'before' => ['roles' => $before],
        'after' => ['roles' => $user->fresh()->getRoleNames()->values()->all()],
        'reason' => "Assigned role {$roleName}",
      ], true);
    });

    return response()->json([
        'success' => true,
        'message' => "Role '{$roleName}' assigned"
    ]);
  }
}
