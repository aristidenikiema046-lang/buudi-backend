<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_subscriptions', function (Blueprint $table) {
            $table->index('driver_id');
            $table->index(['status', 'expires_at']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_online');
        });
    }

    public function down(): void
    {
        Schema::table('driver_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['status', 'expires_at']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_online']);
        });
    }
};
