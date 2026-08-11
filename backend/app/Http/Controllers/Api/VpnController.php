<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\VpnClient;
use App\Models\VpnServer;
use App\Services\AuditLogService;
use App\Services\WireGuardService;
use Illuminate\Http\Request;

class VpnController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WireGuardService $wireGuard,
        private readonly AuditLogService $audit,
    ) {}

    public function servers(Request $request)
    {
        $servers = VpnServer::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount('clients')
            ->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($servers->items(), 'Daftar server VPN berhasil dimuat', [
            'current_page' => $servers->currentPage(), 'last_page' => $servers->lastPage(), 'per_page' => $servers->perPage(), 'total' => $servers->total(),
        ]);
    }

    public function clients(Request $request)
    {
        $clients = VpnClient::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->with('server:id,name,type')->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->success($clients->items(), 'Daftar client VPN berhasil dimuat', [
            'current_page' => $clients->currentPage(), 'last_page' => $clients->lastPage(), 'per_page' => $clients->perPage(), 'total' => $clients->total(),
        ]);
    }

    public function previewClient(Request $request, VpnServer $vpnServer)
    {
        $attributes = $this->validatedClient($request, false);
        $preview = $this->wireGuard->previewClient($vpnServer, $attributes);

        return $this->success($preview, 'Preview client WireGuard mock berhasil dibuat');
    }

    public function provisionClient(Request $request, VpnServer $vpnServer)
    {
        $attributes = $this->validatedClient($request, true);
        $result = $this->wireGuard->provisionClient($vpnServer, $attributes);

        $this->audit->record($request, 'vpn', 'provision_client_mock', $vpnServer, after: [
            'client_id' => $result['client']['id'],
            'name' => $result['client']['name'],
            'assigned_ip' => $result['client']['assigned_ip'],
            'adapter' => 'mock',
            'dry_run' => true,
        ]);

        $response = $this->success($result, 'Client WireGuard mock berhasil dibuat', status: 201);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /** @return array<string, mixed> */
    private function validatedClient(Request $request, bool $requiresConfirmation): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'assigned_ip' => ['nullable', 'ipv4'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($requiresConfirmation) {
            $rules['confirmed'] = ['required', 'accepted'];
        }

        $attributes = $request->validate($rules);
        unset($attributes['confirmed']);

        return $attributes;
    }
}
