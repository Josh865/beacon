<?php

namespace App\Models;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignRecipientDeliveryStatus;
use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'message_body',
        'status',
        'audience_type',
        'scheduled_for',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'audience_type' => CampaignAudienceType::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the campaign.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags associated with the campaign.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the snapshotted recipients associated with the campaign.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Scope a query to the most recently updated campaigns first.
     */
    #[Scope]
    protected function recentFirst(Builder $query): void
    {
        $query->latest('updated_at')->latest('id');
    }

    /**
     * Scope a query to include recipient delivery aggregates.
     */
    #[Scope]
    protected function withRecipientStatistics(Builder $query): void
    {
        $query->withCount(self::recipientCountAggregates());
    }

    /**
     * Scope a query to campaigns owned by a specific user.
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /**
     * Determine if the campaign can still be edited or scheduled.
     */
    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    /**
     * Determine if the campaign can still be scheduled.
     */
    public function canBeScheduled(): bool
    {
        return $this->isDraft();
    }

    /**
     * Get the recipient count aggregates used by the UI.
     *
     * @return array<int|string, mixed>
     */
    public static function recipientCountAggregates(): array
    {
        return [
            'recipients',
            'recipients as pending_recipients_count' => fn (Builder $query) => $query->where(
                'delivery_status',
                CampaignRecipientDeliveryStatus::Pending,
            ),
            'recipients as sent_recipients_count' => fn (Builder $query) => $query->where(
                'delivery_status',
                CampaignRecipientDeliveryStatus::Sent,
            ),
            'recipients as failed_recipients_count' => fn (Builder $query) => $query->where(
                'delivery_status',
                CampaignRecipientDeliveryStatus::Failed,
            ),
            'recipients as skipped_recipients_count' => fn (Builder $query) => $query->where(
                'delivery_status',
                CampaignRecipientDeliveryStatus::Skipped,
            ),
        ];
    }

    /**
     * Resolve the route binding query within the authenticated user's dataset.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = $this->newQuery()->where($field, $value);

        if (auth()->check()) {
            $query->whereBelongsTo(auth()->user());
        }

        $campaign = $query->first();

        if ($campaign === null) {
            throw (new ModelNotFoundException)->setModel(self::class, [$value]);
        }

        return $campaign;
    }
}
