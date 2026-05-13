<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="Проверка состояния сервиса",
     *     tags={"System"},
     *     @OA\Response(
     *         response=200,
     *         description="Сервис работает нормально",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="checks", type="object",
     *                 @OA\Property(property="database", type="string", example="ok")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=503, description="Сервис деградировал")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $dbOk    = $this->checkDatabase();
        $cacheOk = $this->checkCache();

        $healthy = $dbOk && $cacheOk;
        $status  = $healthy ? 'ok' : 'degraded';
        $code    = $healthy ? 200 : 503;

        return response()->json([
            'status'    => $status,
            'timestamp' => now()->toISOString(),
            'checks'    => [
                'database' => $dbOk    ? 'ok' : 'failed',
                'cache'    => $cacheOk ? 'ok' : 'failed',
            ],
        ], $code);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::put('health:ping', true, 5);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
