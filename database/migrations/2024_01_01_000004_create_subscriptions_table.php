<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_uuid');
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->cascadeOnDelete();
            $table->string('billing_source');
            $table->string('lifestream_offer_id');
            $table->string('status');
            $table->boolean('auto_renewal')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['account_uuid', 'billing_source', 'lifestream_offer_id'], 'subscriptions_account_offer_unique');
            $table->index('status');
            $table->index('lifestream_offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
