<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lifestream_transactions';

    protected $primaryKey = 'uuid';

    public const PHASE_ENSURE    = 'ensure';
    public const PHASE_COMMITTED = 'committed';
    public const PHASE_FAILED    = 'failed';

    public const OPERATION_ADD_SUBSCRIPTION     = 'add_subscription';
    public const OPERATION_REPLACE_SUBSCRIPTION = 'replace_subscription';

    protected $fillable = [
        'account_uuid',
        'billing_source',
        'lifestream_offer_id',
        'old_offer_id',
        'operation_type',
        'phase',
        'qs_transaction_id',
        'trial_days',
        'service_start_timestamp',
        'ensure_payload',
        'commit_payload',
        'ensured_at',
        'committed_at',
    ];

    protected $casts = [
        'ensure_payload'          => 'array',
        'commit_payload'          => 'array',
        'trial_days'              => 'integer',
        'service_start_timestamp' => 'datetime',
        'ensured_at'              => 'datetime',
        'committed_at'            => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    public function isCommitted(): bool
    {
        return $this->phase === self::PHASE_COMMITTED;
    }

    public function isFailed(): bool
    {
        return $this->phase === self::PHASE_FAILED;
    }

    public function isPending(): bool
    {
        return $this->phase === self::PHASE_ENSURE;
    }
}
