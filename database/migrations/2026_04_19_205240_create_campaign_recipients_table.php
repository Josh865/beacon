<?php

use App\Enums\CampaignRecipientDeliveryStatus;
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
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->enum('delivery_status', array_map(
                fn (CampaignRecipientDeliveryStatus $status): string => $status->value,
                CampaignRecipientDeliveryStatus::cases(),
            ))->default(CampaignRecipientDeliveryStatus::Pending->value);
            $table->text('delivery_error')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'delivery_status']);
            $table->unique(['campaign_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
