<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RadiusNas;
use App\Services\AuditLogService;
use App\Services\RadiusDiagnosticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RadiusDiagnosticController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RadiusDiagnosticService $radius,
        private readonly AuditLogService $audit,
    ) {}

    public function health()
    {
        return $this->success($this->radius->health(), 'Diagnostik RADIUS berhasil dimuat');
    }

    public function testAuthentication(Request $request)
    {
        $attributes = $request->validate([
            'username' => ['required', 'string', 'max:253'],
            'password' => ['nullable', 'string', 'max:1024'],
            'nas_id' => ['nullable', 'integer', 'exists:radius_nas,id'],
            'simulate' => ['nullable', 'in:accept,reject'],
        ]);

        $result = $this->radius->dryRunAuthentication($attributes);
        $nas = isset($attributes['nas_id']) ? RadiusNas::find($attributes['nas_id']) : null;

        $this->audit->record(
            $request,
            'radius',
            'authentication_test_dry_run',
            $nas,
            after: [
                'username' => $attributes['username'],
                'nas_id' => $attributes['nas_id'] ?? null,
                'driver' => 'mock',
                'dry_run' => true,
                'simulated_result' => $result['simulated_result'],
            ],
        );

        return $this->success($result, 'Simulasi autentikasi RADIUS selesai');
    }

    public function accounting(Request $request)
    {
        $sessions = DB::table('pppoe_sessions')
            ->when($request->filled('username'), fn ($query) => $query->where('username', 'like', '%'.$request->string('username').'%'))
            ->orderByDesc('started_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($sessions->items(), 'Snapshot accounting mock berhasil dimuat', [
            'driver' => 'application_snapshot',
            'radius_database_queried' => false,
            'current_page' => $sessions->currentPage(),
            'last_page' => $sessions->lastPage(),
            'per_page' => $sessions->perPage(),
            'total' => $sessions->total(),
        ]);
    }
}
