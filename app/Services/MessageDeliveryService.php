<?php

namespace App\Services;

use App\Data\MessageDeliveryResult;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;

class MessageDeliveryService
{
    /**
     * Simulate delivery for a campaign contact or recipient snapshot.
     */
    public function deliver(Campaign $campaign, CampaignRecipient|Contact $deliverable): MessageDeliveryResult
    {
        $contact = $deliverable instanceof CampaignRecipient
            ? $deliverable->contact
            : $deliverable;

        if ($contact === null) {
            return MessageDeliveryResult::failure('Recipient contact could not be resolved.');
        }

        if ($contact->user_id !== $campaign->user_id) {
            return MessageDeliveryResult::failure('Recipient contact does not belong to the campaign owner.');
        }

        $fingerprint = implode('|', [
            $campaign->getKey(),
            $contact->getKey(),
            $contact->phone,
            $campaign->message_body,
        ]);

        $checksum = sprintf('%u', crc32($fingerprint));

        if (((int) $checksum % 5) === 0) {
            return MessageDeliveryResult::failure('Simulated provider rejected this message.');
        }

        return MessageDeliveryResult::success("sim-{$campaign->getKey()}-{$contact->getKey()}-{$checksum}");
    }
}
