<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouterRequest;
use App\Http\Resources\RouterResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\PollRouterJob;
use App\Models\AuditLog;
use App\Models\Router;
use App\Services\AuditLogService;
use App\Services\MikroTikService;
use App\Services\SensitiveDataSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RouterController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly MikroTikService $mikroTik,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    public function index(Request $request)
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:unknown,online,offline'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $routers = Router::query()
            ->with(['metrics' => fn ($query) => $query->latest('recorded_at')->limit(1)])
            ->when(isset($input['search']), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$input['search'].'%')->orWhere('host', 'like', '%'.$input['search'].'%')))
            ->when(isset($input['status']), fn ($query) => $query->where('status', $input['status']))
            ->latest()->paginate($input['per_page'] ?? 15);

        return $this->success(RouterResource::collection($routers->items()), 'Daftar router berhasil dimuat', [
            'current_page' => $routers->currentPage(), 'last_page' => $routers->lastPage(), 'per_page' => $routers->perPage(), 'total' => $routers->total(),
        ]);
    }

    public function store(StoreRouterRequest $request)
    {
        $attributes = $request->validated();
        $router = Router::withTrashed()->where('host', $attributes['host'])->first();
        $action = $router ? 'restore_update' : 'create';
        if ($router) {
            $router->restore();
            $router->fill($attributes)->save();
        } else {
            $router = Router::create($attributes);
        }
        $this->audit->record($request, 'routers', $action, $router, after: $router->toArray());

        return $this->success(new RouterResource($router), 'Router berhasil ditambahkan. Password disimpan terenkripsi.', status: 201);
    }

    public function show(Router $router)
    {
        $router->load(['metrics' => fn ($query) => $query->latest('recorded_at')->limit(1)]);
        $router->setAttribute('monitoring_counts', [
            'interfaces_active' => $router->interfaces()->whereNull('missing_since')->where('running', true)->where('disabled', false)->count(),
            'pppoe_active' => $router->pppoeActiveSessions()->whereNull('missing_since')->count(),
            'hotspot_active' => $router->hotspotActiveSessions()->whereNull('missing_since')->count(),
            'dhcp_bound' => $router->dhcpLeases()->whereNull('missing_since')->where('status', 'bound')->count(),
        ]);

        return $this->success(new RouterResource($router));
    }

    public function update(StoreRouterRequest $request, Router $router)
    {
        $before = $router->toArray();
        $attributes = $request->validated();
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }
        $router->update($attributes);
        $this->audit->record($request, 'routers', 'update', $router, $before, $router->fresh()->toArray());

        return $this->success(new RouterResource($router->fresh()), 'Router berhasil diperbarui');
    }

    public function destroy(Request $request, Router $router)
    {
        $before = $router->toArray();
        $router->delete();
        $this->audit->record($request, 'routers', 'delete', $router, $before);

        return $this->success(null, 'Router berhasil dinonaktifkan dari aplikasi');
    }

    public function testConnection(Request $request, Router $router)
    {
        $result = $this->mikroTik->testConnection($router);
        $updates = [
            'status' => $result['success'] ? 'online' : 'offline',
            'last_checked_at' => now(),
            'last_error' => $result['success'] ? null : $this->sanitizer->sanitizeText($result['message'] ?? 'Koneksi RouterOS gagal.'),
            'connection_latency_ms' => $result['success'] ? ($result['latency_ms'] ?? null) : null,
            'capabilities' => array_replace($router->capabilities ?? [], [
                'tcp_ok' => $result['tcp_ok'] ?? (bool) $result['success'],
                'auth_ok' => $result['auth_ok'] ?? (bool) $result['success'],
                'checked_at' => $result['checked_at'] ?? now()->toAtomString(),
                'last_error_code' => $result['error_code'] ?? null,
            ]),
        ];
        if ($result['success']) {
            $updates += [
                'identity' => $result['identity'] ?? $router->identity,
                'routeros_version' => $result['routeros_version'] ?? $router->routeros_version,
                'uptime_seconds' => $result['uptime_seconds'] ?? $router->uptime_seconds,
                'model' => $result['model'] ?? $router->model,
                'serial_number' => $result['serial_number'] ?? $router->serial_number,
                'last_connected_at' => now(),
                'last_seen_at' => now(),
            ];
        }
        $router->update($updates);
        $this->audit->record($request, 'routers', 'test_connection', $router, after: $result, status: $result['success'] ? 'success' : 'failed');

        return $result['success']
            ? $this->success($result, $result['message'])
            : $this->failure($result['message'], ['error_code' => [$result['error_code'] ?? 'connection_failed']], 422);
    }

    public function activePppoe(Router $router)
    {
        return $this->success($this->mikroTik->activePppoeSessions($router), 'Sesi PPPoE aktif berhasil dimuat');
    }

    public function sync(Request $request, Router $router)
    {
        $input = $request->validate(['scope' => ['nullable', 'in:status,resources,active,network,config,full']]);
        if (! $router->enabled || $router->maintenance_mode) {
            return $this->failure('Router tidak aktif atau sedang maintenance.', ['router' => ['Sinkronisasi tidak dijadwalkan.']], 409);
        }
        $scope = $input['scope'] ?? 'full';
        PollRouterJob::dispatch($router->id, $scope);
        $this->audit->record($request, 'routers', 'sync_dispatch', $router, after: ['scope' => $scope]);

        return $this->success(['router_id' => $router->id, 'scope' => $scope], 'Sinkronisasi read-only dimasukkan ke antrean', status: 202);
    }

    public function interfaces(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->interfaces()->whereNull('missing_since')->getQuery(),
            ['name', 'type', 'running', 'dynamic', 'disabled', 'actual_mtu', 'rx_bytes', 'tx_bytes', 'link_downs', 'last_seen_at'],
            ['name', 'type', 'mac_address', 'comment'],
            function (Builder $query, array $input): void {
                match ($input['status'] ?? null) {
                    'online' => $query->where('running', true)->where('disabled', false),
                    'offline' => $query->where('running', false)->where('disabled', false),
                    'disabled' => $query->where('disabled', true),
                    default => null,
                };
            },
        );
    }

    public function pppoeActive(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->pppoeActiveSessions()->whereNull('missing_since')->getQuery(),
            ['name' => 'username', 'service', 'caller_id', 'address', 'uptime' => 'uptime_seconds', 'interface', 'profile', 'last_seen_at'],
            ['username', 'service', 'caller_id', 'address', 'interface', 'profile'],
            transform: fn (array $row): array => ['name' => $row['username']] + $row,
        );
    }

    public function pppSecrets(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->pppSecrets()->whereNull('missing_since')->getQuery(),
            ['name' => 'username', 'service', 'profile', 'local_address', 'remote_address', 'disabled', 'last_seen_at'],
            ['username', 'service', 'profile', 'local_address', 'remote_address', 'comment'],
            function (Builder $query, array $input): void {
                if (($input['status'] ?? null) === 'disabled') {
                    $query->where('disabled', true);
                } elseif (in_array($input['status'] ?? null, ['online', 'active'], true)) {
                    $query->where('disabled', false);
                }
            },
            fn (array $row): array => ['name' => $row['username']] + $row,
        );
    }

    public function pppProfiles(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->pppProfiles()->whereNull('missing_since')->getQuery(),
            ['name', 'local_address', 'remote_address_pool' => 'remote_address', 'rate_limit', 'only_one', 'last_seen_at'],
            ['name', 'local_address', 'remote_address', 'rate_limit'],
            transform: fn (array $row): array => [
                'remote_address_pool' => $row['remote_address'],
                'session_timeout' => $row['settings']['session-timeout'] ?? null,
            ] + $row,
        );
    }

    public function hotspotActive(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->hotspotActiveSessions()->whereNull('missing_since')->getQuery(),
            ['user' => 'username', 'address', 'mac_address', 'server', 'login_by', 'uptime' => 'uptime_seconds', 'last_seen_at'],
            ['username', 'address', 'mac_address', 'server', 'login_by'],
            transform: fn (array $row): array => ['user' => $row['username']] + $row,
        );
    }

    public function hotspotUsers(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->hotspotUsers()->whereNull('missing_since')->getQuery(),
            ['name' => 'username', 'profile', 'server', 'mac_address', 'disabled', 'limit_uptime', 'last_seen_at'],
            ['username', 'profile', 'server', 'mac_address', 'comment'],
            function (Builder $query, array $input): void {
                if (($input['status'] ?? null) === 'disabled') {
                    $query->where('disabled', true);
                } elseif (in_array($input['status'] ?? null, ['online', 'active'], true)) {
                    $query->where('disabled', false);
                }
            },
            fn (array $row): array => ['name' => $row['username']] + $row,
        );
    }

    public function dhcpLeases(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->dhcpLeases()->whereNull('missing_since')->getQuery(),
            ['address', 'mac_address', 'host_name', 'status', 'server', 'dynamic', 'expires_after', 'last_seen_at'],
            ['address', 'mac_address', 'host_name', 'server', 'comment'],
            function (Builder $query, array $input): void {
                if (filled($input['status'] ?? null)) {
                    $query->where('status', $input['status']);
                }
            },
        );
    }

    public function queues(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->queues()->whereNull('missing_since')->getQuery(),
            ['name', 'type' => 'kind', 'target', 'parent', 'max_limit', 'rate', 'disabled', 'last_seen_at'],
            ['name', 'target', 'parent', 'queue_type', 'comment'],
            function (Builder $query, array $input): void {
                $kind = match ($input['type'] ?? null) {
                    'simple' => 'queue_simple',
                    'tree' => 'queue_tree',
                    'type' => 'queue_types',
                    default => null,
                };
                if ($kind) {
                    $query->where('kind', $kind);
                }
            },
            fn (array $row): array => ['type' => match ($row['kind']) {
                'queue_simple' => 'simple',
                'queue_tree' => 'tree',
                'queue_types' => 'type',
                default => $row['kind'],
            }] + $row,
        );
    }

    public function syncHistory(Request $request, Router $router)
    {
        return $this->snapshotPage(
            $request,
            $router->syncRuns()->getQuery(),
            ['correlation_id', 'scope', 'status', 'started_at', 'finished_at', 'duration_ms', 'created_at'],
            ['correlation_id', 'scope', 'status', 'error'],
            function (Builder $query, array $input): void {
                if (filled($input['status'] ?? null)) {
                    $query->where('status', $input['status']);
                }
            },
            fn (array $row): array => [
                'items_synced' => collect($row['counts'] ?? [])->sum(),
                'error_summary' => $this->sanitizer->sanitizeText($row['error'] ?? null),
            ] + $row,
        );
    }

    public function auditLogs(Request $request, Router $router)
    {
        $response = $this->snapshotPage(
            $request,
            AuditLog::query()->with('user:id,name')->where('target_type', Router::class)->where('target_id', (string) $router->id),
            ['action', 'status', 'correlation_id', 'user_id', 'created_at'],
            ['action', 'status', 'correlation_id', 'ip_address', 'error'],
            function (Builder $query, array $input): void {
                if (filled($input['status'] ?? null)) {
                    $query->where('status', $input['status']);
                }
            },
            function (array $row): array {
                $user = $row['user'] ?? null;
                unset($row['user']);

                return [
                    'actor' => $user ? ['id' => $user['id'], 'name' => $user['name']] : ['id' => null, 'name' => 'Sistem'],
                    'before' => $this->sanitizer->sanitizeArray($row['before'] ?? null),
                    'after' => $this->sanitizer->sanitizeArray($row['after'] ?? null),
                    'error' => $this->sanitizer->sanitizeText($row['error'] ?? null),
                ] + $row;
            },
        );

        return $response;
    }

    private function snapshotPage(
        Request $request,
        Builder $query,
        array $allowedSorts,
        array $searchColumns,
        ?callable $filter = null,
        ?callable $transform = null,
    ) {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        if (filled($input['search'] ?? null)) {
            $search = $input['search'];
            $query->where(function (Builder $nested) use ($search, $searchColumns): void {
                foreach ($searchColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}($column, 'like', '%'.$search.'%');
                }
            });
        }
        $filter?->__invoke($query, $input);

        $sortMap = [];
        foreach ($allowedSorts as $alias => $column) {
            $sortMap[is_int($alias) ? $column : $alias] = $column;
        }
        $sort = $sortMap[$input['sort'] ?? ''] ?? (reset($sortMap) ?: 'id');
        $direction = $input['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderBy('id');
        }
        $paginator = $query->paginate($input['per_page'] ?? 15);
        $items = collect($paginator->items())->map(function ($model) use ($transform): array {
            $row = $model->toArray();

            return $transform ? $transform($row) : $row;
        })->all();

        return $this->success($items, 'Snapshot router berhasil dimuat', [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]);
    }
}
