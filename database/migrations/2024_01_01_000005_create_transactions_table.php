<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid');
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->cascadeOnDelete();
            $table->string('billing_source');
            $table->string('lifestream_offer_id');
            $table->string('old_offer_id')->nullable();
            $table->string('operation_type');
            $table->string('phase');
            $table->string('qs_transaction_id')->nullable()->unique();
            $table->integer('trial_days')->nullable();
            $table->timestamp('service_start_timestamp')->nullable();
            $table->json('ensure_payload')->nullable();
            $table->json('commit_payload')->nullable();
            $table->timestamp('ensured_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['account_uuid', 'phase']);
            $table->index('billing_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
