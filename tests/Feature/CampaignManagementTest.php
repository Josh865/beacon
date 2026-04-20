<?php

use App\Enums\CampaignStatus;
use App\Jobs\ProcessScheduledCampaign;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('user can create a draft campaign', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('campaigns.store'), [
        'user_id' => User::factory()->create()->id,
        'name' => 'Wednesday Prayer Reminder',
        'message_body' => 'Join us this Wednesday at 6:30 PM for prayer in the fellowship hall.',
        'status' => 'scheduled',
        'audience_type' => 'all_contacts',
        'tag_ids' => [],
    ]);

    $campaign = Campaign::query()->first();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('campaigns.edit', $campaign));

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'user_id' => $user->id,
        'name' => 'Wednesday Prayer Reminder',
        'status' => 'draft',
        'audience_type' => 'all_contacts',
    ]);
});

test('user can update their own draft campaign', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->forUser($user)->create([
        'name' => 'Volunteers',
        'slug' => 'volunteers',
    ]);
    $campaign = Campaign::factory()->forUser($user)->create([
        'name' => 'Initial draft',
        'message_body' => 'Original draft body.',
        'audience_type' => 'all_contacts',
    ]);

    $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
        'name' => 'Volunteer Appreciation',
        'message_body' => 'Thank you for serving this Sunday. Please arrive 15 minutes early.',
        'status' => 'draft',
        'audience_type' => 'tag_selection',
        'tag_ids' => [$tag->id],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('campaigns.edit', $campaign));

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'user_id' => $user->id,
        'name' => 'Volunteer Appreciation',
        'audience_type' => 'tag_selection',
        'status' => 'draft',
    ]);

    expect($campaign->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('user cannot view or edit another users campaign', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->forUser($otherUser)->create();

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('campaigns.edit', $campaign))
        ->assertNotFound();
});

test('previewing audience count for all contacts only counts the signed in users active contacts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Contact::factory()->count(2)->forUser($user)->active()->create();
    Contact::factory()->forUser($user)->inactive()->create();
    Contact::factory()->count(3)->forUser($otherUser)->active()->create();

    $this->actingAs($user)
        ->postJson(route('campaigns.preview-audience'), [
            'audience_type' => 'all_contacts',
            'tag_ids' => [],
        ])
        ->assertSuccessful()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('audience_type', 'all_contacts')
        ->assertJsonPath('implemented', true);
});

test('previewing audience count for selected tags only includes the signed in users contacts and tags', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $members = Tag::factory()->forUser($user)->create(['name' => 'Members', 'slug' => 'members']);
    $leaders = Tag::factory()->forUser($user)->create(['name' => 'Leaders', 'slug' => 'leaders']);
    $otherUsersMembers = Tag::factory()->forUser($otherUser)->create([
        'name' => 'Members',
        'slug' => 'members',
    ]);

    $memberContact = Contact::factory()->forUser($user)->active()->create();
    $memberContact->tags()->sync([$members->id]);

    $leaderContact = Contact::factory()->forUser($user)->active()->create();
    $leaderContact->tags()->sync([$leaders->id]);

    $multiTaggedContact = Contact::factory()->forUser($user)->active()->create();
    $multiTaggedContact->tags()->sync([$members->id, $leaders->id]);

    $inactiveContact = Contact::factory()->forUser($user)->inactive()->create();
    $inactiveContact->tags()->sync([$members->id]);

    $otherUsersContact = Contact::factory()->forUser($otherUser)->active()->create();
    $otherUsersContact->tags()->sync([$otherUsersMembers->id]);

    $this->actingAs($user)
        ->postJson(route('campaigns.preview-audience'), [
            'audience_type' => 'tag_selection',
            'tag_ids' => [$members->id, $leaders->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('count', 3)
        ->assertJsonPath('audience_type', 'tag_selection')
        ->assertJsonPath('implemented', true);
});

test('manual selection audience cannot be stored through a crafted request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('campaigns.create'))
        ->post(route('campaigns.store'), [
            'name' => 'Manual attempt',
            'message_body' => 'This should be rejected.',
            'status' => 'draft',
            'audience_type' => 'manual_selection',
            'tag_ids' => [],
        ])
        ->assertSessionHasErrors('audience_type')
        ->assertRedirect(route('campaigns.create'));

    expect(Campaign::query()->count())->toBe(0);
});

test('manual selection audience cannot be previewed through a crafted request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('campaigns.preview-audience'), [
            'audience_type' => 'manual_selection',
            'tag_ids' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audience_type');
});

test('validation requires tags when audience type is tag selection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('campaigns.create'))
        ->post(route('campaigns.store'), [
            'name' => 'Members update',
            'message_body' => 'Important update for members.',
            'status' => 'draft',
            'audience_type' => 'tag_selection',
            'tag_ids' => [],
        ])
        ->assertSessionHasErrors('tag_ids')
        ->assertRedirect(route('campaigns.create'));
});

test('user cannot attach another users tags to their campaign', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();
    $foreignTag = Tag::factory()->forUser($otherUser)->create([
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    $this->actingAs($user)
        ->from(route('campaigns.edit', $campaign))
        ->put(route('campaigns.update', $campaign), [
            'name' => $campaign->name,
            'message_body' => $campaign->message_body,
            'status' => 'draft',
            'audience_type' => 'tag_selection',
            'tag_ids' => [$foreignTag->id],
        ])
        ->assertSessionHasErrors('tag_ids.0')
        ->assertRedirect(route('campaigns.edit', $campaign));

    expect($campaign->fresh()->tags)->toHaveCount(0);
});

test('scheduled campaigns cannot be updated after leaving draft', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->put(route('campaigns.update', $campaign), [
            'name' => 'Blocked update',
            'message_body' => 'Updated copy',
            'status' => 'draft',
            'audience_type' => 'all_contacts',
            'tag_ids' => [],
        ])
        ->assertForbidden();

    expect($campaign->fresh()->name)->not->toBe('Blocked update');
});

test('user cannot schedule another users campaign', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->forUser($otherUser)->create();

    $this->actingAs($user)
        ->post(route('campaigns.schedule', $campaign), [
            'scheduled_for' => now()->addHour()->toIso8601String(),
        ])
        ->assertNotFound();
});

test('cannot schedule a non draft campaign', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->from(route('campaigns.show', $campaign))
        ->post(route('campaigns.schedule', $campaign), [
            'scheduled_for' => now()->addDays(2)->toIso8601String(),
        ])
        ->assertForbidden();
});

test('cannot schedule a campaign in the past', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->from(route('campaigns.show', $campaign))
        ->post(route('campaigns.schedule', $campaign), [
            'scheduled_for' => now()->subMinute()->toIso8601String(),
        ])
        ->assertSessionHasErrors('scheduled_for')
        ->assertRedirect(route('campaigns.show', $campaign));
});

test('cannot schedule a campaign with zero recipients', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->from(route('campaigns.show', $campaign))
        ->post(route('campaigns.schedule', $campaign), [
            'scheduled_for' => now()->addHour()->toIso8601String(),
        ])
        ->assertSessionHasErrors('audience')
        ->assertRedirect(route('campaigns.show', $campaign));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
    $this->assertDatabaseCount('campaign_recipients', 0);
});

test('scheduling a campaign snapshots only the campaign owners contacts', function () {
    Queue::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();

    $ownerContact = Contact::factory()->forUser($user)->active()->create();
    Contact::factory()->forUser($user)->inactive()->create();
    $otherContact = Contact::factory()->forUser($otherUser)->active()->create();

    $this->actingAs($user)->post(route('campaigns.schedule', $campaign), [
        'scheduled_for' => now()->addHour()->toIso8601String(),
    ])->assertRedirect(route('campaigns.show', $campaign));

    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'contact_id' => $ownerContact->id,
    ]);
    $this->assertDatabaseMissing('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'contact_id' => $otherContact->id,
    ]);
    $this->assertDatabaseCount('campaign_recipients', 1);
});

test('scheduling updates campaign status and scheduled for', function () {
    Queue::fake();

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();
    Contact::factory()->forUser($user)->active()->create();
    $scheduledFor = Carbon::parse(now()->addHours(2)->startOfMinute()->toIso8601String());

    $this->actingAs($user)->post(route('campaigns.schedule', $campaign), [
        'scheduled_for' => $scheduledFor->toIso8601String(),
    ])->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Scheduled);
    expect($campaign->scheduled_for?->equalTo($scheduledFor))->toBeTrue();
});

test('scheduling dispatches the queued job with delay', function () {
    Queue::fake();

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create();
    Contact::factory()->forUser($user)->active()->create();
    $scheduledFor = Carbon::parse(now()->addHour()->startOfMinute()->toIso8601String());

    $this->actingAs($user)->post(route('campaigns.schedule', $campaign), [
        'scheduled_for' => $scheduledFor->toIso8601String(),
    ])->assertRedirect(route('campaigns.show', $campaign));

    Queue::assertPushed(ProcessScheduledCampaign::class, function (ProcessScheduledCampaign $job) use ($campaign, $scheduledFor) {
        return $job->campaignId === $campaign->id
            && $job->delay instanceof Carbon
            && $job->delay->equalTo($scheduledFor);
    });
});

test('duplicate contact rows are not created for overlapping tag matches', function () {
    Queue::fake();

    $user = User::factory()->create();
    $members = Tag::factory()->forUser($user)->create(['name' => 'Members', 'slug' => 'members']);
    $leaders = Tag::factory()->forUser($user)->create(['name' => 'Leaders', 'slug' => 'leaders']);
    $campaign = Campaign::factory()->forUser($user)->tagSelection()->create();
    $campaign->tags()->sync([$members->id, $leaders->id]);

    $contact = Contact::factory()->forUser($user)->active()->create();
    $contact->tags()->sync([$members->id, $leaders->id]);

    $this->actingAs($user)->post(route('campaigns.schedule', $campaign), [
        'scheduled_for' => now()->addHour()->toIso8601String(),
    ])->assertRedirect(route('campaigns.show', $campaign));

    $this->assertDatabaseCount('campaign_recipients', 1);
    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
    ]);
});

test('campaign index includes recipient statistics and only allows editing drafts', function () {
    $user = User::factory()->create();

    $draftCampaign = Campaign::factory()->forUser($user)->create([
        'name' => 'Editable draft',
        'status' => CampaignStatus::Draft,
    ]);
    $sentCampaign = Campaign::factory()->forUser($user)->create([
        'name' => 'Already sent',
        'status' => CampaignStatus::Sent,
        'sent_at' => now(),
    ]);

    $draftCampaign->recipients()->create([
        'contact_id' => Contact::factory()->forUser($user)->active()->create()->id,
        'delivery_status' => 'pending',
    ]);

    $sentCampaign->recipients()->createMany([
        [
            'contact_id' => Contact::factory()->forUser($user)->active()->create()->id,
            'delivery_status' => 'sent',
            'processed_at' => now()->subMinute(),
        ],
        [
            'contact_id' => Contact::factory()->forUser($user)->active()->create()->id,
            'delivery_status' => 'failed',
            'delivery_error' => 'Simulated provider failure.',
            'processed_at' => now(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->has('campaigns.data', 2)
            ->where('campaigns.data.0.name', 'Already sent')
            ->where('campaigns.data.0.can_edit', false)
            ->where('campaigns.data.0.recipient_count', 2)
            ->where('campaigns.data.0.delivery_counts.sent', 1)
            ->where('campaigns.data.0.delivery_counts.failed', 1)
            ->where('campaigns.data.1.name', 'Editable draft')
            ->where('campaigns.data.1.can_edit', true)
            ->where('campaigns.data.1.delivery_counts.pending', 1),
        );
});

test('recipient membership remains snapshotted after scheduling even if contacts stop matching later', function () {
    Queue::fake();

    $user = User::factory()->create();
    $members = Tag::factory()->forUser($user)->create(['name' => 'Members', 'slug' => 'members']);
    $campaign = Campaign::factory()->forUser($user)->tagSelection()->create();
    $campaign->tags()->sync([$members->id]);

    $contact = Contact::factory()->forUser($user)->active()->create();
    $contact->tags()->sync([$members->id]);

    $this->actingAs($user)
        ->post(route('campaigns.schedule', $campaign), [
            'scheduled_for' => now()->addHour()->toIso8601String(),
        ])
        ->assertRedirect(route('campaigns.show', $campaign));

    $contact->update(['status' => 'inactive']);
    $contact->tags()->sync([]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.recipient_count', 1)
            ->where('audiencePreview.count', 0)
            ->where('campaign.can_schedule', false)
            ->has('recipients.data', 1),
        );
});

test('campaign detail page shows delivery summary and recipient rows for the owner', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Sent,
        'scheduled_for' => now()->subHour(),
        'sent_at' => now(),
    ]);

    $sentContact = Contact::factory()->forUser($user)->active()->create([
        'first_name' => 'Ava',
        'last_name' => 'Morris',
        'phone' => '317-555-1101',
    ]);
    $failedContact = Contact::factory()->forUser($user)->active()->create([
        'first_name' => 'Noah',
        'last_name' => 'Bryant',
        'phone' => '317-555-1102',
    ]);

    $campaign->recipients()->createMany([
        [
            'contact_id' => $sentContact->id,
            'delivery_status' => 'sent',
            'processed_at' => now()->subMinutes(10),
        ],
        [
            'contact_id' => $failedContact->id,
            'delivery_status' => 'failed',
            'delivery_error' => 'Simulated provider failure.',
            'processed_at' => now()->subMinutes(9),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.delivery_counts.sent', 1)
            ->where('campaign.delivery_counts.failed', 1)
            ->where('campaign.delivery_counts.pending', 0)
            ->where('campaign.recipient_count', 2)
            ->has('recipients.data', 2)
            ->has('recipients.links')
            ->where('recipients.data.0.delivery_status', 'failed')
            ->where('recipients.data.1.delivery_status', 'sent'),
        );
});

test('campaign detail includes paginator links for recipient pagination ui', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Sent,
        'scheduled_for' => now()->subHour(),
        'sent_at' => now(),
    ]);

    $contacts = Contact::factory()->count(26)->forUser($user)->active()->create();

    foreach ($contacts as $contact) {
        $campaign->recipients()->create([
            'contact_id' => $contact->id,
            'delivery_status' => 'sent',
            'processed_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('campaigns.show', ['campaign' => $campaign, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('recipients.current_page', 2)
            ->where('recipients.last_page', 2)
            ->where('recipients.links.1.label', '1')
            ->where('recipients.links.2.label', '2')
            ->where('recipients.links.2.active', true),
        );
});
