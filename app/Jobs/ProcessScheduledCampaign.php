<?php

namespace App\Jobs;

use App\Data\MessageDeliveryResult;
use App\Enums\CampaignRecipientDeliveryStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\MessageDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessScheduledCampaign implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignId) {}

    /**
     * Execute the job.
     */
    public function handle(MessageDeliveryService $messageDeliveryService): void
    {
        $campaign = DB::transaction(function (): ?Campaign {
            /** @var Campaign|null $campaign */
            $campaign = Campaign::query()
                ->with('user:id')
                ->lockForUpdate()
                ->find($this->campaignId);

            if ($campaign === null) {
                return null;
            }

            if ($campaign->status !== CampaignStatus::Scheduled) {
                return null;
            }

            $campaign->forceFill([
                'status' => CampaignStatus::Processing,
            ])->save();

            return $campaign;
        });

        if ($campaign === null) {
            return;
        }

        $campaign->recipients()
            ->where('delivery_status', CampaignRecipientDeliveryStatus::Pending)
            ->with(['contact:id,user_id,first_name,last_name,phone'])
            ->lazyById(100, column: 'id')
            ->each(function (CampaignRecipient $recipient) use ($campaign, $messageDeliveryService): void {
                $deliveryResult = $recipient->contact?->user_id === $campaign->user_id
                    ? $messageDeliveryService->deliver($campaign, $recipient)
                    : MessageDeliveryResult::failure('Recipient contact does not belong to the campaign owner.');

                $this->markRecipientAsProcessed($recipient, $deliveryResult);
            });

        $pendingRecipientsRemain = $campaign->recipients()
            ->where('delivery_status', CampaignRecipientDeliveryStatus::Pending)
            ->exists();

        if ($pendingRecipientsRemain) {
            return;
        }

        $campaign->forceFill([
            'status' => CampaignStatus::Sent,
            'sent_at' => now(),
        ])->save();
    }

    /**
     * Mark an individual recipient as processed using the simulated delivery result.
     */
    protected function markRecipientAsProcessed(
        CampaignRecipient $recipient,
        MessageDeliveryResult $deliveryResult,
    ): void {
        $recipient->forceFill([
            'delivery_status' => $deliveryResult->successful
                ? CampaignRecipientDeliveryStatus::Sent
                : CampaignRecipientDeliveryStatus::Failed,
            'delivery_error' => $deliveryResult->error,
            'processed_at' => now(),
        ])->save();
    }
}
