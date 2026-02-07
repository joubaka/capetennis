<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
  /**
   * Display a listing of users (DataTables / AJAX).
   */
  public function index()
  {
    $users = User::with('roles')->get();

    return response()->json([
      'data' => $users
    ]);
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
   * Display the specified resource.
   * (Not used currently)
   */
  public function show(User $user)
  {
    abort(404);
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
    $user->delete();

    return response()->json([
      'success' => true,
      'message' => 'User deleted'
    ]);
  }

  /**
   * Remove admin role.
   */
  public function removeRole($id)
  {
    $user = User::findOrFail($id);
    $user->removeRole('admin');

    return response()->json([
      'success' => true,
      'message' => 'Admin role removed'
    ]);
  }

  /**
   * Add admin role.
   */
  public function addRole($id)
  {
    $user = User::findOrFail($id);
    $user->assignRole('admin');

    return response()->json([
      'success' => true,
      'message' => 'Admin role added'
    ]);
  }
}
