<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'account_uuid',
        'mac',
        'synced_to_lifestream',
    ];

    protected $casts = [
        'synced_to_lifestream' => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    public static function normalizeMac(string $mac): string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return implode(':', str_split($clean, 2));
    }
}
