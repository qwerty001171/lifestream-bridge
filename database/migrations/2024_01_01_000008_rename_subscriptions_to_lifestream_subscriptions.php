<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('subscriptions', 'lifestream_subscriptions');
    }

    public function down(): void
    {
        Schema::rename('lifestream_subscriptions', 'subscriptions');
    }
};
