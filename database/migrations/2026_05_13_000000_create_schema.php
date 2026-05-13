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

        Schema::create('lifestream_offers', function (Blueprint $table) {
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

        Schema::create('lifestream_subscriptions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid');
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->cascadeOnDelete();
            $table->string('lifestream_offer_id');
            $table->string('status');
            $table->boolean('auto_renewal')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['account_uuid', 'lifestream_offer_id'], 'subscriptions_account_offer_unique');
            $table->index('status');
            $table->index('lifestream_offer_id');
        });

        Schema::create('lifestream_transactions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid');
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->cascadeOnDelete();
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
        });

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
        Schema::dropIfExists('lifestream_transactions');
        Schema::dropIfExists('lifestream_subscriptions');
        Schema::dropIfExists('lifestream_offers');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('accounts');
    }
};
