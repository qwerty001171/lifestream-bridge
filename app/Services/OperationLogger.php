<?php

namespace App\Services;

use App\Models\OperationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationLogger
{
    public function log(
        string $operationType,
        string $result,
        ?string $accountId = null,
        ?string $billingSource = null,
        array $data = [],
        ?string $errorMessage = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        try {
            $sanitizedData = $this->sanitize($data);

            OperationLog::create([
                'account_uuid'     => $accountId,
                'billing_source' => $billingSource,
                'operation_type' => $operationType,
                'operation_data' => $sanitizedData ?: null,
                'ip_address'     => $ip,
                'user_agent'     => $userAgent,
                'result'         => $result,
                'error_message'  => $errorMessage,
            ]);
        } catch (Throwable $e) {
            Log::error('OperationLogger: failed to write log entry', [
                'operation_type' => $operationType,
                'result'         => $result,
                'account_uuid'     => $accountId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    private function sanitize(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_hash',
            'passwd',
            'secret',
            'token',
            'api_key',
            'apikey',
            'authorization',
            'auth',
            'credential',
        ];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
