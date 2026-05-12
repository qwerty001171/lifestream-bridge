<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('external_id');
            $table->string('billing_source');
            $table->string('login');
            $table->string('email')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('lifestream_id')->nullable();
            $table->unsignedBigInteger('mid')->nullable();
            $table->string('mac')->nullable();
            $table->string('paket', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'billing_source']);
            $table->index('billing_source');
            $table->index('lifestream_id');
            $table->index('login');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
