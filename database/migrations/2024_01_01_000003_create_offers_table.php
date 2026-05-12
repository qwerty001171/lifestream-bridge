<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('billing_source');
            $table->string('billing_package_code');
            $table->string('lifestream_offer_id');
            $table->string('name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('auto_renewal')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['billing_source', 'billing_package_code']);
            $table->index('billing_source');
            $table->index('lifestream_offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
