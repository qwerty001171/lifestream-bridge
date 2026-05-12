<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'external_id',
        'billing_source',
        'login',
        'email',
        'password_hash',
        'lifestream_id',
        'mid',
        'mac',
        'paket',
        'last_synced_at',
    ];

    protected $casts = [
        'mid'            => 'integer',
        'paket'          => 'string',
        'last_synced_at' => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'account_uuid', 'uuid');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'account_uuid', 'uuid');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_uuid', 'uuid');
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(OperationLog::class, 'account_uuid', 'uuid');
    }

    public function isSyncedToLifestream(): bool
    {
        return $this->lifestream_id !== null;
    }
}
