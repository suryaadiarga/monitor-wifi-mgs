<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Services\SensitiveDataSanitizer;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,acknowledged,resolved'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $alerts = Alert::query()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['severity']), fn ($query) => $query->where('severity', $validated['severity']))
            ->when(isset($validated['search']), function ($query) use ($validated): void {
                $search = $validated['search'];
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%'));
            })
            ->latest('started_at')
            ->paginate($validated['per_page'] ?? 25);

        $items = collect($alerts->items())->map(fn (Alert $alert): array => [
            'id' => $alert->id,
            'alert_rule_id' => $alert->alert_rule_id,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'source_type' => $alert->source_type,
            'source_id' => $alert->source_id,
            'title' => $this->sanitizer->sanitizeText($alert->title),
            'message' => $this->sanitizer->sanitizeText($alert->message),
            'fingerprint' => $alert->fingerprint,
            'started_at' => $alert->started_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ])->all();

        return $this->success($items, 'Daftar alert berhasil dimuat', [
            'current_page' => $alerts->currentPage(),
            'last_page' => $alerts->lastPage(),
            'per_page' => $alerts->perPage(),
            'total' => $alerts->total(),
        ]);
    }
}
