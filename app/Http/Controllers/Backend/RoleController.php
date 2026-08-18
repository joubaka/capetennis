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
    $request->validate(['name' => 'required|string|unique:roles,name']);
    $role = DB::transaction(function () use ($request) {
      $role = Role::create(['name' => $request->name]);
      app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'role.created',
        'subject' => $role,
        'after' => ['name' => $role->name],
      ], true);
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
    DB::transaction(function () use ($role): void {
      $before = $role->toArray();
      $role->delete();
      app(AuditWriter::class)->record([
        'category' => 'security',
        'action' => 'role.deleted',
        'subject_type' => Role::class,
        'subject_id' => $before['id'],
        'subject_label' => $before['name'],
        'before' => $before,
      ], true);
    });

    return response()->json([
      'success' => true,
      'message' => 'Role deleted'
    ]);
  }
}
