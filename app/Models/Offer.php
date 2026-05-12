<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';

    protected $table = 'lifestream_offers';

    protected $fillable = [
        'billing_source',
        'billing_package_code',
        'lifestream_offer_id',
        'name',
        'price',
        'auto_renewal',
        'is_active',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'auto_renewal' => 'boolean',
        'is_active'    => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRegion($query, string $region)
    {
        return $query->where('billing_source', $region);
    }

    public static function findLifestreamOfferId(string $billingSource, string $packageCode): ?string
    {
        return static::where('billing_source', $billingSource)
            ->where('billing_package_code', $packageCode)
            ->where('is_active', true)
            ->value('lifestream_offer_id');
    }
}
