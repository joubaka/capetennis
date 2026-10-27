<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\AccountStatementController as StatementController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountStatementService;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $input = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($input['search'] ?? '');
        $users = User::query()->select(['id', 'name', 'userName', 'userSurname', 'email'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('userName', 'like', '%'.$search.'%')
                    ->orWhere('userSurname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();

        return response()->view('backend.account-statements.index', compact('users', 'search'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, User $user, AccountStatementService $service, StatementController $statements)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);

        return $statements->forAccount($request, $service, $user, true);
    }
}
