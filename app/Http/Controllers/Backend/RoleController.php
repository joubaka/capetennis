<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use App\Support\Audit\AuditWriter;

class RoleController extends Controller
{
  public function store(Request $request)
  {
    abort_unless($request->user()?->hasRole('super-user'), 403);
    $request->validate(['name' => 'required|string|unique:roles,name']);
    $role = DB::transaction(function () use ($request) {
      $role = Role::create(['name' => $request->name]);
      $audit = app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'role.created',
        'subject' => $role,
        'after' => ['name' => $role->name],
      ], true);
      if (!$audit) throw new \RuntimeException('The role audit could not be saved. No role was created.');
      return $role;
    });

    return response()->json([
      'success' => true,
      'role' => $role,
      'message' => 'Role created'
    ]);
  }

  public function destroy(Role $role)
  {
    abort_unless(request()->user()?->hasRole('super-user'), 403);
    DB::transaction(function () use ($role): void {
      $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
      $before = $role->toArray();
      $role->delete();
      $audit = app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'role.deleted',
        'subject_type' => Role::class,
        'subject_id' => $before['id'],
        'subject_label' => $before['name'],
        'before' => $before,
      ], true);
      if (!$audit) throw new \RuntimeException('The role audit could not be saved. No role was deleted.');
    });

    return response()->json([
      'success' => true,
      'message' => 'Role deleted'
    ]);
  }
}
