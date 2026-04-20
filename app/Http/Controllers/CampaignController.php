<?php

namespace App\Http\Controllers;

use App\Actions\Campaigns\ResolveCampaignAudience;
use App\Actions\Campaigns\ScheduleCampaign;
use App\Enums\CampaignAudienceType;
use App\Http\Requests\PreviewCampaignAudienceRequest;
use App\Http\Requests\ScheduleCampaignRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        protected ResolveCampaignAudience $resolveCampaignAudience,
    ) {}

    /**
     * Display a listing of campaigns.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = $request->user()->campaigns()
            ->withRecipientStatistics()
            ->with(['tags:id,name,slug'])
            ->recentFirst()
            ->paginate(12)
            ->through(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'audience_type' => $campaign->audience_type->value,
                'audience_label' => $campaign->audience_type->label(),
                'scheduled_for' => $campaign->scheduled_for?->toIso8601String(),
                'recipient_count' => $campaign->recipients_count ?? 0,
                'delivery_counts' => [
                    'pending' => $campaign->pending_recipients_count ?? 0,
                    'sent' => $campaign->sent_recipients_count ?? 0,
                    'failed' => $campaign->failed_recipients_count ?? 0,
                ],
                'updated_at' => $campaign->updated_at->toIso8601String(),
                'can_edit' => $campaign->isDraft(),
                'tags' => $campaign->tags
                    ->sortBy('name')
                    ->values()
                    ->map(fn (Tag $tag): array => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ]),
            ]);

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('campaigns/create', [
            'campaign' => $this->defaultCampaignData(),
            'tags' => $this->tagOptions($request->user()),
            'audienceTypes' => $this->audienceTypeOptions(),
            'audiencePreview' => $this->audiencePreview(
                $request->user(),
                CampaignAudienceType::AllContacts,
                [],
            ),
        ]);
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = $request->user()->campaigns()->create($request->campaignData());
        $campaign->tags()->sync($request->tagIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign draft created.')]);

        return to_route('campaigns.edit', $campaign);
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);
        $campaign->load(['tags:id,name,slug'])->loadCount(Campaign::recipientCountAggregates());

        $recipients = $campaign->recipients()
            ->with(['contact:id,user_id,first_name,last_name,phone'])
            ->select(['id', 'campaign_id', 'contact_id', 'delivery_status', 'delivery_error', 'processed_at'])
            ->latest('id')
            ->paginate(25)
            ->through(fn (CampaignRecipient $recipient): array => [
                'id' => $recipient->id,
                'contact' => $recipient->contact === null ? null : [
                    'id' => $recipient->contact->id,
                    'name' => $recipient->contact->full_name,
                    'phone' => $recipient->contact->phone,
                ],
                'delivery_status' => $recipient->delivery_status->value,
                'delivery_error' => $recipient->delivery_error,
                'processed_at' => $recipient->processed_at?->toIso8601String(),
            ]);

        return Inertia::render('campaigns/show', [
            'campaign' => $this->campaignResource($campaign),
            'recipients' => $recipients,
            'audiencePreview' => $this->audiencePreview(
                request()->user(),
                $campaign->audience_type,
                $campaign->tags->pluck('id')->all(),
            ),
        ]);
    }

    /**
     * Show the form for editing the specified campaign.
     */
    public function edit(Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);
        $campaign->load(['tags:id,name,slug']);

        return Inertia::render('campaigns/edit', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'message_body' => $campaign->message_body,
                'status' => $campaign->status->value,
                'audience_type' => $campaign->audience_type->value,
                'tag_ids' => $campaign->tags->pluck('id')->all(),
            ],
            'tags' => $this->tagOptions(request()->user()),
            'audienceTypes' => $this->audienceTypeOptions(),
            'audiencePreview' => $this->audiencePreview(
                request()->user(),
                $campaign->audience_type,
                $campaign->tags->pluck('id')->all(),
            ),
        ]);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $campaign->update($request->campaignData());
        $campaign->tags()->sync($request->tagIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign draft updated.')]);

        return to_route('campaigns.edit', $campaign);
    }

    /**
     * Schedule the specified campaign for future processing.
     */
    public function schedule(
        ScheduleCampaignRequest $request,
        Campaign $campaign,
        ScheduleCampaign $scheduleCampaign,
    ): RedirectResponse {
        $this->authorize('schedule', $campaign);

        $scheduledCampaign = $scheduleCampaign->handle($campaign, $request->scheduledFor());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign scheduled.')]);

        return to_route('campaigns.show', $scheduledCampaign);
    }

    /**
     * Preview the audience count for the provided campaign filters.
     */
    public function previewAudience(PreviewCampaignAudienceRequest $request): JsonResponse
    {
        return response()->json(
            $this->audiencePreview($request->user(), $request->audienceType(), $request->tagIds()),
        );
    }

    /**
     * Get the default campaign values used by the UI.
     *
     * @return array{
     *     name: string,
     *     message_body: string,
     *     status: string,
     *     audience_type: string,
     *     tag_ids: list<int>
     * }
     */
    protected function defaultCampaignData(): array
    {
        return [
            'name' => '',
            'message_body' => '',
            'status' => 'draft',
            'audience_type' => 'all_contacts',
            'tag_ids' => [],
        ];
    }

    /**
     * Transform a campaign for the show page.
     *
     * @return array<string, mixed>
     */
    protected function campaignResource(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'message_body' => $campaign->message_body,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'audience_type' => $campaign->audience_type->value,
            'audience_label' => $campaign->audience_type->label(),
            'scheduled_for' => $campaign->scheduled_for?->toIso8601String(),
            'sent_at' => $campaign->sent_at?->toIso8601String(),
            'recipient_count' => $campaign->recipients_count ?? $campaign->recipients()->count(),
            'delivery_counts' => [
                'pending' => $campaign->pending_recipients_count ?? 0,
                'sent' => $campaign->sent_recipients_count ?? 0,
                'failed' => $campaign->failed_recipients_count ?? 0,
                'skipped' => $campaign->skipped_recipients_count ?? 0,
            ],
            'created_at' => $campaign->created_at->toIso8601String(),
            'updated_at' => $campaign->updated_at->toIso8601String(),
            'can_edit' => $campaign->isDraft(),
            'can_schedule' => $campaign->canBeScheduled(),
            'tags' => $campaign->tags
                ->sortBy('name')
                ->values()
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ]),
        ];
    }

    /**
     * Get the tag options used by the UI.
     *
     * @return Collection<int, array{id: int, name: string, slug: string}>
     */
    protected function tagOptions(User $user): Collection
    {
        return $user->tags()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]);
    }

    /**
     * Get the audience type options used by the UI.
     *
     * @return list<array{value: string, label: string, implemented: bool}>
     */
    protected function audienceTypeOptions(): array
    {
        return [
            [
                'value' => CampaignAudienceType::AllContacts->value,
                'label' => CampaignAudienceType::AllContacts->label(),
                'implemented' => CampaignAudienceType::AllContacts->isImplemented(),
            ],
            [
                'value' => CampaignAudienceType::TagSelection->value,
                'label' => CampaignAudienceType::TagSelection->label(),
                'implemented' => CampaignAudienceType::TagSelection->isImplemented(),
            ],
            [
                'value' => CampaignAudienceType::ManualSelection->value,
                'label' => CampaignAudienceType::ManualSelection->label(),
                'implemented' => CampaignAudienceType::ManualSelection->isImplemented(),
            ],
        ];
    }

    /**
     * Calculate the audience preview for a campaign draft.
     *
     * @param  list<int>  $tagIds
     * @return array{count: int, audience_type: string, audience_label: string, implemented: bool}
     */
    protected function audiencePreview(User $user, CampaignAudienceType $audienceType, array $tagIds): array
    {
        return [
            'count' => $this->resolveCampaignAudience->count($user, $audienceType, $tagIds),
            'audience_type' => $audienceType->value,
            'audience_label' => $audienceType->label(),
            'implemented' => $audienceType->isImplemented(),
        ];
    }
}
