<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid');
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->cascadeOnDelete();
            $table->string('mac');
            $table->boolean('synced_to_lifestream')->default(false);
            $table->timestamps();

            $table->unique(['account_uuid', 'mac']);
            $table->index('mac');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
