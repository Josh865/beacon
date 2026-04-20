<?php

namespace App\Enums;

enum CampaignAudienceType: string
{
    case AllContacts = 'all_contacts';
    case TagSelection = 'tag_selection';
    case ManualSelection = 'manual_selection';

    /**
     * Determine if the audience type is available in this slice.
     */
    public function isImplemented(): bool
    {
        return $this !== self::ManualSelection;
    }

    /**
     * Get the audience types currently supported by the product.
     *
     * @return list<string>
     */
    public static function implementedValues(): array
    {
        return array_map(
            fn (self $audienceType): string => $audienceType->value,
            array_values(array_filter(
                self::cases(),
                fn (self $audienceType): bool => $audienceType->isImplemented(),
            )),
        );
    }

    /**
     * Get the label used in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::AllContacts => 'All contacts',
            self::TagSelection => 'Tagged contacts',
            self::ManualSelection => 'Manual selection',
        };
    }
}
