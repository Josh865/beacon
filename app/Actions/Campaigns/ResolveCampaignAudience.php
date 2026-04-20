<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResolveCampaignAudience
{
    /**
     * Get the current audience count for the provided campaign filters.
     */
    public function count(User $user, CampaignAudienceType $audienceType, array $tagIds = []): int
    {
        return $this->query($user, $audienceType, $tagIds)->count();
    }

    /**
     * Get distinct contact IDs for the provided campaign filters.
     *
     * @param  list<int>  $tagIds
     * @return list<int>
     */
    public function contactIds(User $user, CampaignAudienceType $audienceType, array $tagIds = []): array
    {
        /** @var list<int> $contactIds */
        $contactIds = $this->query($user, $audienceType, $tagIds)
            ->select('contacts.id')
            ->distinct()
            ->pluck('contacts.id')
            ->all();

        return $contactIds;
    }

    /**
     * Build the owner-scoped contact query for a campaign audience.
     *
     * @param  list<int>  $tagIds
     */
    public function query(User $user, CampaignAudienceType $audienceType, array $tagIds = []): HasMany
    {
        $query = $user->contacts()->active();

        return match ($audienceType) {
            CampaignAudienceType::AllContacts => $query,
            CampaignAudienceType::TagSelection => $this->applyTagSelection($query, $tagIds),
            CampaignAudienceType::ManualSelection => $query->whereRaw('0 = 1'),
        };
    }

    /**
     * Constrain the query to contacts that match any selected tag.
     *
     * @param  list<int>  $tagIds
     */
    protected function applyTagSelection(HasMany $query, array $tagIds): HasMany
    {
        if ($tagIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('tags', fn ($query) => $query->whereKey($tagIds));
    }
}
