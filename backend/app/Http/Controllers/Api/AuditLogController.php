<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Services\SensitiveDataSanitizer;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:success,failed'],
            'user_id' => ['nullable', 'integer'],
            'correlation_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->when(isset($validated['module']), fn ($query) => $query->where('module', $validated['module']))
            ->when(isset($validated['action']), fn ($query) => $query->where('action', $validated['action']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['user_id']), fn ($query) => $query->where('user_id', $validated['user_id']))
            ->when(isset($validated['correlation_id']), fn ($query) => $query->where('correlation_id', $validated['correlation_id']))
            ->when(isset($validated['date_from']), fn ($query) => $query->where('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->where('created_at', '<=', $validated['date_to']))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 25);

        $items = collect($logs->items())->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'correlation_id' => $log->correlation_id,
            'user_id' => $log->user_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $this->sanitizer->sanitizeText($log->user_agent),
            'module' => $log->module,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'before' => $this->sanitizer->sanitizeArray($log->before),
            'after' => $this->sanitizer->sanitizeArray($log->after),
            'status' => $log->status,
            'error' => $this->sanitizer->sanitizeText($log->error),
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all();

        return $this->success($items, 'Daftar audit log berhasil dimuat', [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }
}
