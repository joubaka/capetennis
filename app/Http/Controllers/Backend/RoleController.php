<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
  public function store(Request $request)
  {
    $request->validate(['name' => 'required|string|unique:roles,name']);
    $role = Role::create(['name' => $request->name]);

    return response()->json([
      'success' => true,
      'role' => $role,
      'message' => 'Role created'
    ]);
  }

  public function destroy(Role $role)
  {
    $role->delete();

    return response()->json([
      'success' => true,
      'message' => 'Role deleted'
    ]);
  }
}
