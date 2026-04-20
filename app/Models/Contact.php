<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
        ];
    }

    /**
     * Get the tags assigned to the contact.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Get the user that owns the contact.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the campaign recipient snapshots for this contact.
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Get the contact's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Scope a query to only include active contacts.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ContactStatus::Active->value);
    }

    /**
     * Scope a query by status when provided.
     */
    #[Scope]
    protected function withStatus(Builder $query, null|string|ContactStatus $status): void
    {
        if ($status instanceof ContactStatus) {
            $status = $status->value;
        }

        if (blank($status)) {
            return;
        }

        $query->where('status', $status);
    }

    /**
     * Scope a query to match a free-text search.
     */
    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = trim($search);

        $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('tags', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Scope a query by tag when provided.
     */
    #[Scope]
    protected function withTag(Builder $query, ?int $tagId): void
    {
        if (blank($tagId)) {
            return;
        }

        $query->whereHas('tags', fn (Builder $query) => $query->whereKey($tagId));
    }

    /**
     * Scope a query to contacts owned by a specific user.
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
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

        $contact = $query->first();

        if ($contact === null) {
            throw (new ModelNotFoundException)->setModel(self::class, [$value]);
        }

        return $contact;
    }
}
