<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $audit) {}

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $this->audit->record($request, 'authentication', 'login', status: 'failed', error: 'Credential tidak valid');

            return $this->failure('Email atau password salah.', ['email' => ['Credential tidak valid.']], 422);
        }

        $token = $user->createToken($request->string('device_name', 'web')->toString())->plainTextToken;
        $this->audit->record($request, 'authentication', 'login', $user);

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Login berhasil');
    }

    public function me(Request $request)
    {
        return $this->success($this->userPayload($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        $this->audit->record($request, 'authentication', 'logout', $request->user());

        return $this->success(null, 'Logout berhasil');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->user()->tokens()->whereKeyNot($request->user()->currentAccessToken()?->getKey())->delete();
        $this->audit->record($request, 'authentication', 'change_password', $request->user());

        return $this->success(null, 'Password berhasil diubah');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return $this->success(null, 'Jika email terdaftar, tautan reset password akan dikirim.');
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });

        return $status === Password::PasswordReset
            ? $this->success(null, 'Password berhasil direset. Silakan login kembali.')
            : $this->failure('Token reset password tidak valid atau sudah kedaluwarsa.', ['token' => [__($status)]]);
    }

    public function sessions(Request $request)
    {
        return $this->success($request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'is_current' => $request->user()->currentAccessToken()?->getKey() === $token->id,
        ]));
    }

    public function revokeSession(Request $request, int $token)
    {
        $session = $request->user()->tokens()->findOrFail($token);
        $session->delete();
        $this->audit->record($request, 'authentication', 'revoke_session', $request->user(), after: ['token_id' => $token]);

        return $this->success(null, 'Sesi berhasil dicabut');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
