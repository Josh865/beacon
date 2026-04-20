<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->index(['user_id', 'name']);
        });

        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->index(['tag_id', 'contact_id']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('campaign_tag', function (Blueprint $table): void {
            $table->index(['tag_id', 'campaign_id']);
        });

        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->index(['campaign_id', 'id']);
            $table->index(['campaign_id', 'delivery_status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropIndex(['campaign_id', 'delivery_status', 'id']);
            $table->dropIndex(['campaign_id', 'id']);
        });

        Schema::table('campaign_tag', function (Blueprint $table): void {
            $table->dropIndex(['tag_id', 'campaign_id']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'updated_at']);
        });

        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->dropIndex(['tag_id', 'contact_id']);
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'name']);
        });
    }
};
