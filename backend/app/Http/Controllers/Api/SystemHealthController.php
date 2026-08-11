<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SystemHealthService;

class SystemHealthController extends Controller
{
    use ApiResponse;

    public function __invoke(SystemHealthService $health)
    {
        $result = $health->check();

        return $this->success($result, $result['healthy'] ? 'Sistem sehat' : 'Sebagian layanan bermasalah', status: $result['healthy'] ? 200 : 503);
    }
}
