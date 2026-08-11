<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Olt;
use App\Services\AuditLogService;
use App\Services\OltService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OltController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly OltService $olts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $olts = Olt::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('host', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('vendor'), fn ($query) => $query->where('vendor', (string) $request->string('vendor')))
            ->latest()
            ->paginate($perPage);

        return $this->success($olts->items(), 'Daftar OLT berhasil dimuat', [
            'current_page' => $olts->currentPage(),
            'last_page' => $olts->lastPage(),
            'per_page' => $olts->perPage(),
            'total' => $olts->total(),
        ]);
    }

    public function testConnection(Request $request, Olt $olt): JsonResponse
    {
        try {
            $result = $this->olts->testConnection($olt);
        } catch (RuntimeException) {
            $message = 'Pengujian koneksi OLT ditolak karena driver nyata belum terverifikasi.';
            $this->audit->record($request, 'olts', 'test_connection', $olt, status: 'failed', error: $message);

            return $this->failure($message, ['code' => 'olt_driver_unavailable'], 503);
        }

        $result = $this->audit->sanitize($result) ?? [];
        $connected = (bool) ($result['success'] ?? false);

        $olt->update([
            'status' => $connected ? 'online' : 'offline',
            'last_seen_at' => $connected ? now() : $olt->last_seen_at,
        ]);

        $this->audit->record(
            $request,
            'olts',
            'test_connection',
            $olt,
            after: $result,
            status: $connected ? 'success' : 'failed',
        );

        return $this->success($result, (string) ($result['message'] ?? 'Pengujian koneksi OLT selesai'));
    }

    public function onts(Request $request, Olt $olt): JsonResponse
    {
        try {
            $onts = collect($this->olts->listOnts($olt));
        } catch (RuntimeException) {
            return $this->failure(
                'Inventory ONT tidak dapat dimuat karena driver OLT nyata belum terverifikasi.',
                ['code' => 'olt_driver_unavailable'],
                503,
            );
        }

        if ($request->filled('status')) {
            $status = strtolower((string) $request->string('status'));
            $onts = $onts->filter(fn (array $ont): bool => strtolower((string) ($ont['status'] ?? '')) === $status);
        }

        if ($request->filled('search')) {
            $search = strtolower(trim((string) $request->string('search')));
            $onts = $onts->filter(function (array $ont) use ($search): bool {
                return str_contains(strtolower((string) ($ont['serial_number'] ?? '')), $search)
                    || str_contains(strtolower((string) ($ont['onu_id'] ?? '')), $search);
            });
        }

        return $this->success($onts->values()->all(), 'Inventory ONT berhasil dimuat', [
            'total' => $onts->count(),
        ]);
    }
}
