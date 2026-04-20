<?php

use App\Data\MessageDeliveryResult;
use App\Enums\CampaignRecipientDeliveryStatus;
use App\Enums\CampaignStatus;
use App\Jobs\ProcessScheduledCampaign;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\User;
use App\Services\MessageDeliveryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('processing a scheduled campaign marks it processing before it is marked sent', function () {
    $now = Carbon::parse('2026-04-19 10:00:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);

    $contacts = Contact::factory()->count(2)->forUser($user)->active()->create();

    foreach ($contacts as $contact) {
        $campaign->recipients()->create([
            'contact_id' => $contact->id,
            'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
        ]);
    }

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('deliver')
            ->twice()
            ->andReturnUsing(function (Campaign $campaign): MessageDeliveryResult {
                expect($campaign->status)->toBe(CampaignStatus::Processing);

                return MessageDeliveryResult::success('sim-processing-check');
            });
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Sent);
    expect($campaign->sent_at?->equalTo($now))->toBeTrue();

    Carbon::setTestNow();
});

test('successful delivery updates recipient rows correctly', function () {
    $now = Carbon::parse('2026-04-19 11:15:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);
    $contact = Contact::factory()->forUser($user)->active()->create();

    $recipient = $campaign->recipients()->create([
        'contact_id' => $contact->id,
        'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
    ]);

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock) use ($recipient): void {
        $mock->shouldReceive('deliver')
            ->once()
            ->withArgs(fn (Campaign $campaign, $deliverable): bool => $campaign->is($recipient->campaign) && $deliverable->is($recipient))
            ->andReturn(MessageDeliveryResult::success('sim-recipient-success'));
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($recipient->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Sent);
    expect($recipient->fresh()->delivery_error)->toBeNull();
    expect($recipient->fresh()->processed_at?->equalTo($now))->toBeTrue();

    Carbon::setTestNow();
});

test('failed delivery stores the error message', function () {
    $now = Carbon::parse('2026-04-19 12:30:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);
    $contact = Contact::factory()->forUser($user)->active()->create();

    $recipient = $campaign->recipients()->create([
        'contact_id' => $contact->id,
        'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
    ]);

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('deliver')
            ->once()
            ->andReturn(MessageDeliveryResult::failure('Simulated downstream failure.'));
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    $recipient->refresh();
    $campaign->refresh();

    expect($recipient->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Failed);
    expect($recipient->delivery_error)->toBe('Simulated downstream failure.');
    expect($recipient->processed_at?->equalTo($now))->toBeTrue();
    expect($campaign->status)->toBe(CampaignStatus::Sent);

    Carbon::setTestNow();
});

test('cancelled campaigns do not process', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Cancelled,
    ]);
    $contact = Contact::factory()->forUser($user)->active()->create();

    $recipient = $campaign->recipients()->create([
        'contact_id' => $contact->id,
        'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
    ]);

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('deliver');
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Cancelled);
    expect($recipient->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Pending);
    expect($recipient->fresh()->processed_at)->toBeNull();
});

test('already sent campaigns do not process twice', function () {
    $sentAt = Carbon::parse('2026-04-19 09:45:00');

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Sent,
        'scheduled_for' => $sentAt->copy()->subHour(),
        'sent_at' => $sentAt,
    ]);
    $contact = Contact::factory()->forUser($user)->active()->create();

    $recipient = $campaign->recipients()->create([
        'contact_id' => $contact->id,
        'delivery_status' => CampaignRecipientDeliveryStatus::Sent,
        'processed_at' => $sentAt,
    ]);

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('deliver');
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent);
    expect($campaign->fresh()->sent_at?->equalTo($sentAt))->toBeTrue();
    expect($recipient->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Sent);
});

test('recipient processing works correctly for multiple rows', function () {
    $now = Carbon::parse('2026-04-19 13:45:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);

    $contacts = Contact::factory()->count(3)->forUser($user)->active()->create();
    $recipients = collect();

    foreach ($contacts as $contact) {
        $recipients->push($campaign->recipients()->create([
            'contact_id' => $contact->id,
            'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
        ]));
    }

    $results = [
        $recipients[0]->id => MessageDeliveryResult::success('sim-first'),
        $recipients[1]->id => MessageDeliveryResult::failure('Second delivery failed.'),
        $recipients[2]->id => MessageDeliveryResult::success('sim-third'),
    ];

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock) use ($results): void {
        $mock->shouldReceive('deliver')
            ->times(3)
            ->andReturnUsing(fn (Campaign $campaign, $deliverable): MessageDeliveryResult => $results[$deliverable->id]);
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($recipients[0]->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Sent);
    expect($recipients[1]->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Failed);
    expect($recipients[1]->fresh()->delivery_error)->toBe('Second delivery failed.');
    expect($recipients[2]->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Sent);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent);

    Carbon::setTestNow();
});

test('recipients whose contacts do not belong to the campaign owner are failed safely', function () {
    $now = Carbon::parse('2026-04-19 14:00:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);
    $foreignContact = Contact::factory()->forUser($otherUser)->active()->create();

    $recipient = $campaign->recipients()->create([
        'contact_id' => $foreignContact->id,
        'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
    ]);

    $this->mock(MessageDeliveryService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('deliver');
    });

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($recipient->fresh()->delivery_status)->toBe(CampaignRecipientDeliveryStatus::Failed);
    expect($recipient->fresh()->delivery_error)->toBe('Recipient contact does not belong to the campaign owner.');
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent);

    Carbon::setTestNow();
});

test('the delivery service can be faked through the container in tests', function () {
    $now = Carbon::parse('2026-04-19 14:30:00');
    Carbon::setTestNow($now);

    $user = User::factory()->create();
    $campaign = Campaign::factory()->forUser($user)->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_for' => $now->copy()->subMinute(),
    ]);
    $contacts = Contact::factory()->count(2)->forUser($user)->active()->create();

    foreach ($contacts as $contact) {
        $campaign->recipients()->create([
            'contact_id' => $contact->id,
            'delivery_status' => CampaignRecipientDeliveryStatus::Pending,
        ]);
    }

    $fakeService = new class extends MessageDeliveryService
    {
        public int $deliveries = 0;

        public function deliver(Campaign $campaign, CampaignRecipient|Contact $deliverable): MessageDeliveryResult
        {
            $this->deliveries++;

            return MessageDeliveryResult::success("fake-{$deliverable->id}");
        }
    };

    app()->instance(MessageDeliveryService::class, $fakeService);

    (new ProcessScheduledCampaign($campaign->id))->handle(app(MessageDeliveryService::class));

    expect($fakeService->deliveries)->toBe(2);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent);
    expect($campaign->recipients()->where('delivery_status', CampaignRecipientDeliveryStatus::Sent)->count())->toBe(2);

    Carbon::setTestNow();
});
