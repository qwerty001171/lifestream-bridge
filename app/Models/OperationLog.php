<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationLog extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const TYPE_SYNC_MAIN_BILLING = 'sync_main_billing';
    public const TYPE_SYNC_LIFESTREAM   = 'sync_lifestream';
    public const TYPE_UPSALE_ENSURE     = 'upsale_ensure';
    public const TYPE_UPSALE_COMMIT     = 'upsale_commit';
    public const TYPE_PASSWORD_SYNC     = 'password_sync';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_FAILED  = 'failed';

    protected $table = 'lifestream_operation_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_uuid',
        'billing_source',
        'operation_type',
        'operation_data',
        'ip_address',
        'user_agent',
        'result',
        'error_message',
    ];

    protected $casts = [
        'operation_data' => 'array',
        'created_at'     => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }
}
