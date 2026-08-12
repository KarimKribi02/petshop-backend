<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends DashboardController
{
    /**
     * Entrypoint for /api/v1/admin/dashboard/stats & /api/v1/admin/dashboard-stats
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->getStats($request);
    }
}
