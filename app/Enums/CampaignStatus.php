<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    /**
     * Get the label used in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Cancelled => 'Cancelled',
        };
    }
}
