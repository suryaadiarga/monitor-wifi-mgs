<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotspotController extends Controller
{
    use ApiResponse;

    public function users(Request $request)
    {
        $users = DB::table('hotspot_users')
            ->select(['id', 'hotspot_server_id', 'hotspot_profile_id', 'customer_id', 'username', 'mac_address', 'expires_at', 'disabled', 'created_at', 'updated_at'])
            ->whereNull('deleted_at')
            ->when($request->filled('search'), fn ($query) => $query->where('username', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('id')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($users->items(), 'Daftar user Hotspot berhasil dimuat', [
            'current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total(),
        ]);
    }

    public function sessions(Request $request)
    {
        $sessions = DB::table('hotspot_sessions')
            ->when($request->boolean('active_only', true), fn ($query) => $query->whereNull('ended_at'))
            ->orderByDesc('started_at')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($sessions->items(), 'Sesi Hotspot berhasil dimuat', [
            'current_page' => $sessions->currentPage(), 'last_page' => $sessions->lastPage(), 'per_page' => $sessions->perPage(), 'total' => $sessions->total(),
        ]);
    }
}
