<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index()
    {
        return $this->success(User::with('roles:id,name')->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'unique:users'], 'password' => ['required', 'string', 'min:12'], 'role' => ['required', 'exists:roles,name']]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        $user->assignRole($data['role']);
        $this->audit->record($request, 'users', 'create', $user, after: ['name' => $user->name, 'email' => $user->email, 'role' => $data['role']]);

        return $this->success($user->load('roles'), 'User berhasil dibuat', status: 201);
    }

    public function roles()
    {
        return $this->success(Role::with('permissions:id,name')->get());
    }
}
