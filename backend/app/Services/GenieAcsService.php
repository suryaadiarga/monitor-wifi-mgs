<?php

namespace App\Services;

use App\Models\GenieAcsDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GenieAcsService
{
    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    /**
     * @param  array{search?: string, status?: string, manufacturer?: string, product_class?: string}  $filters
     */
    public function listDevices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = min(max($perPage, 1), 100);

        $devices = GenieAcsDevice::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('device_id', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('manufacturer', 'like', "%{$search}%")
                        ->orWhere('product_class', 'like', "%{$search}%")
                        ->orWhere('wan_ip', 'like', "%{$search}%");
                });
            })
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', (string) $filters['status']),
            )
            ->when(
                filled($filters['manufacturer'] ?? null),
                fn ($query) => $query->where('manufacturer', (string) $filters['manufacturer']),
            )
            ->when(
                filled($filters['product_class'] ?? null),
                fn ($query) => $query->where('product_class', (string) $filters['product_class']),
            )
            ->orderByDesc('last_inform_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $devices->setCollection(
            $devices->getCollection()->map(fn (GenieAcsDevice $device): array => $this->serializeDevice($device)),
        );

        return $devices;
    }

    /** @return array<string, mixed> */
    public function device(GenieAcsDevice $device): array
    {
        return $this->serializeDevice($device);
    }

    /** @return array<string, mixed> */
    private function serializeDevice(GenieAcsDevice $device): array
    {
        $data = $device->toArray();
        $data['parameters'] = $this->sanitizer->sanitizeArray($device->parameters);

        return $data;
    }
}
