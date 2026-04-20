<?php

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('message_body');
            $table->enum('status', array_map(
                fn (CampaignStatus $status): string => $status->value,
                CampaignStatus::cases(),
            ));
            $table->enum('audience_type', array_map(
                fn (CampaignAudienceType $audienceType): string => $audienceType->value,
                CampaignAudienceType::cases(),
            ));
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('scheduled_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
