<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\OntDevice;
use App\Models\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $data = Cache::remember('dashboard:v1:summary', now()->addSeconds(30), function (): array {
            $latestMetrics = DB::table('router_metrics as rm')
                ->joinSub(DB::table('router_metrics')->selectRaw('router_id, MAX(recorded_at) as max_recorded_at')->groupBy('router_id'), 'latest', function ($join) {
                    $join->on('rm.router_id', '=', 'latest.router_id')->on('rm.recorded_at', '=', 'latest.max_recorded_at');
                });

            return [
                'counts' => [
                    'routers_total' => Router::count(),
                    'routers_online' => Router::where('status', 'online')->count(),
                    'routers_offline' => Router::where('status', 'offline')->count(),
                    'olts_total' => Olt::count(),
                    'olts_online' => Olt::where('status', 'online')->count(),
                    'customers_total' => Customer::count(),
                    'customers_active' => Customer::where('status', 'aktif')->count(),
                    'pppoe_online' => DB::table('pppoe_sessions')->whereNull('ended_at')->distinct()->count('username'),
                    'pppoe_offline' => max(0, DB::table('pppoe_accounts')->where('disabled', false)->count() - DB::table('pppoe_sessions')->whereNull('ended_at')->distinct()->count('username')),
                    'hotspot_active' => DB::table('hotspot_sessions')->whereNull('ended_at')->count(),
                    'ont_online' => OntDevice::where('status', 'online')->count(),
                    'ont_offline' => OntDevice::where('status', 'offline')->count(),
                    'ont_los' => OntDevice::where('status', 'los')->count(),
                    'ont_low_optical' => OntDevice::where('status', 'online')->where('optical_rx', '<', -27)->count(),
                    'genieacs_devices' => DB::table('genieacs_devices')->count(),
                    'vpn_active' => DB::table('vpn_clients')->where('enabled', true)->whereNull('deleted_at')->count(),
                    'alerts_open' => Alert::where('status', 'open')->count(),
                ],
                'traffic' => [
                    'download_bps' => (int) (clone $latestMetrics)->sum('rm.download_bps'),
                    'upload_bps' => (int) (clone $latestMetrics)->sum('rm.upload_bps'),
                    'avg_cpu_percent' => round((float) (clone $latestMetrics)->avg('rm.cpu_percent'), 2),
                    'avg_memory_percent' => round((float) (clone $latestMetrics)->avg('rm.memory_percent'), 2),
                ],
                'alerts' => Alert::where('status', 'open')->latest('started_at')->limit(8)->get(),
                'activities' => AuditLog::latest()->limit(8)->get(['id', 'user_id', 'module', 'action', 'status', 'created_at']),
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return $this->success($data, 'Dashboard berhasil dimuat');
    }
}
