<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignRecipientDeliveryStatus;
use App\Enums\CampaignStatus;
use App\Jobs\ProcessScheduledCampaign;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleCampaign
{
    public function __construct(
        public ResolveCampaignAudience $resolveCampaignAudience,
    ) {}

    /**
     * Schedule a draft campaign and snapshot its recipients.
     *
     * @throws ValidationException
     */
    public function handle(Campaign $campaign, CarbonInterface $scheduledFor): Campaign
    {
        return DB::transaction(function () use ($campaign, $scheduledFor): Campaign {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::query()
                ->with(['user:id', 'tags:id'])
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCampaign->isDraft()) {
                throw ValidationException::withMessages([
                    'campaign' => 'Only draft campaigns can be scheduled.',
                ]);
            }

            if ($scheduledFor->isPast() || $scheduledFor->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'scheduled_for' => 'Choose a future date and time for this campaign.',
                ]);
            }

            $recipientContactIds = $this->resolveCampaignAudience->contactIds(
                $lockedCampaign->user,
                $lockedCampaign->audience_type,
                $lockedCampaign->tags->modelKeys(),
            );

            if ($recipientContactIds === []) {
                throw ValidationException::withMessages([
                    'audience' => 'This campaign has no eligible recipients to schedule.',
                ]);
            }

            $lockedCampaign->forceFill([
                'status' => CampaignStatus::Scheduled,
                'scheduled_for' => $scheduledFor,
            ])->save();

            $timestamp = now();

            CampaignRecipient::query()->insert(
                collect($recipientContactIds)
                    ->unique()
                    ->values()
                    ->map(fn (int $contactId): array => [
                        'campaign_id' => $lockedCampaign->id,
                        'contact_id' => $contactId,
                        'delivery_status' => CampaignRecipientDeliveryStatus::Pending->value,
                        'delivery_error' => null,
                        'processed_at' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->all(),
            );

            ProcessScheduledCampaign::dispatch($lockedCampaign->id)
                ->delay($scheduledFor)
                ->afterCommit();

            /** @var Campaign $scheduledCampaign */
            $scheduledCampaign = $lockedCampaign->fresh(['tags:id,name,slug']);
            $scheduledCampaign->loadCount('recipients');

            return $scheduledCampaign;
        });
    }
}
