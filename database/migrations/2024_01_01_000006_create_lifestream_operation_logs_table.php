<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifestream_operation_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid')->nullable();
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->nullOnDelete();
            $table->string('billing_source')->nullable();
            $table->string('operation_type');
            $table->json('operation_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('result');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['operation_type', 'result']);
            $table->index('billing_source');
            $table->index('account_uuid');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifestream_operation_logs');
    }
};
