<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metric = $this->relationLoaded('metrics') ? $this->metrics->first() : null;
        $resource = $metric ? [
            'cpu_percent' => $metric->cpu_percent,
            'cpu_count' => $metric->cpu_count,
            'memory_percent' => $metric->memory_percent,
            'total_memory' => $metric->total_memory_bytes,
            'free_memory' => $metric->free_memory_bytes,
            'storage_percent' => $metric->storage_percent,
            'total_storage' => $metric->total_storage_bytes,
            'free_storage' => $metric->free_storage_bytes,
            'temperature_celsius' => $metric->temperature_celsius,
            'voltage' => $metric->voltage_volts,
            'uptime_seconds' => $metric->uptime_seconds,
            'recorded_at' => $metric->recorded_at,
        ] : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'api_port' => $this->use_ssl ? $this->api_ssl_port : $this->api_port,
            'api_plain_port' => $this->api_port,
            'api_ssl_port' => $this->api_ssl_port,
            'use_ssl' => $this->use_ssl,
            'verify_tls' => $this->verify_tls,
            'tls_fingerprint_configured' => filled($this->certificate_fingerprint),
            'username' => $this->username,
            'credential_configured' => filled($this->getRawOriginal('password')),
            'location' => $this->location,
            'identity' => $this->identity,
            'model' => $this->model,
            'board_name' => $this->board_name,
            'architecture' => $this->architecture_name,
            'serial_number' => $this->serial_number,
            'routeros_version' => $this->routeros_version,
            'uptime_seconds' => $this->uptime_seconds,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at,
            'last_checked_at' => $this->last_checked_at,
            'last_connected_at' => $this->last_connected_at,
            'last_successful_sync_at' => $this->last_successful_sync_at,
            'last_error' => $this->last_error,
            'latency_ms' => $this->connection_latency_ms,
            'capabilities' => $this->capabilities,
            'maintenance_mode' => $this->maintenance_mode,
            'enabled' => $this->enabled,
            'counts' => $this->getAttribute('monitoring_counts'),
            'latest_metric' => $resource,
            'latest_resource' => $resource,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
