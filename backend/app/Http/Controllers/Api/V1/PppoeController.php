<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PppoeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PppoeController extends Controller
{
    use ApiResponse;

    public function accounts(Request $request)
    {
        $accounts = PppoeAccount::query()
            ->when($request->filled('search'), fn ($query) => $query->where('username', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('router_id'), fn ($query) => $query->where('router_id', $request->integer('router_id')))
            ->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($accounts->items(), 'Daftar akun PPPoE berhasil dimuat', [
            'current_page' => $accounts->currentPage(), 'last_page' => $accounts->lastPage(), 'per_page' => $accounts->perPage(), 'total' => $accounts->total(),
        ]);
    }

    public function sessions(Request $request)
    {
        $sessions = DB::table('pppoe_sessions')
            ->when($request->boolean('active_only', true), fn ($query) => $query->whereNull('ended_at'))
            ->when($request->filled('router_id'), fn ($query) => $query->where('router_id', $request->integer('router_id')))
            ->orderByDesc('started_at')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($sessions->items(), 'Sesi PPPoE berhasil dimuat', [
            'current_page' => $sessions->currentPage(), 'last_page' => $sessions->lastPage(), 'per_page' => $sessions->perPage(), 'total' => $sessions->total(),
        ]);
    }

    public function reconciliation(Request $request)
    {
        $items = PppoeAccount::query()->limit(min(max($request->integer('limit', 100), 1), 500))->get()->map(fn (PppoeAccount $account) => [
            'account_id' => $account->id,
            'username' => $account->username,
            'application' => ['exists' => true, 'profile' => $account->profile, 'disabled' => $account->disabled],
            'mikrotik' => ['available' => false, 'exists' => null],
            'radius' => ['available' => false, 'exists' => null],
            'status' => 'password_tidak_dapat_diverifikasi',
            'message' => 'Adapter nyata belum terhubung; tidak ada perubahan yang dijalankan.',
        ]);

        return $this->success($items, 'Preview reconciliation PPPoE berhasil dibuat', ['dry_run' => true, 'total' => $items->count()]);
    }
}
