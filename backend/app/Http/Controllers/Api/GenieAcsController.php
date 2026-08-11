<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\GenieAcsDevice;
use App\Services\GenieAcsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenieAcsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GenieAcsService $genieAcs) {}

    public function index(Request $request): JsonResponse
    {
        $devices = $this->genieAcs->listDevices([
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
            'manufacturer' => (string) $request->string('manufacturer'),
            'product_class' => (string) $request->string('product_class'),
        ], $request->integer('per_page', 15));

        return $this->success($devices->items(), 'Daftar perangkat GenieACS berhasil dimuat', [
            'current_page' => $devices->currentPage(),
            'last_page' => $devices->lastPage(),
            'per_page' => $devices->perPage(),
            'total' => $devices->total(),
        ]);
    }

    public function show(GenieAcsDevice $device): JsonResponse
    {
        return $this->success(
            $this->genieAcs->device($device),
            'Detail perangkat GenieACS berhasil dimuat',
        );
    }
}
