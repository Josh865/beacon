<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('authenticated user can create a contact and it is assigned automatically', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('contacts.store'), [
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Maria',
        'last_name' => 'Henderson',
        'email' => 'maria@example.com',
        'phone' => '317-555-0123',
        'status' => 'active',
        'notes' => 'Coordinates volunteer follow-up.',
        'tag_ids' => [],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contacts.index'));

    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'first_name' => 'Maria',
        'last_name' => 'Henderson',
        'email' => 'maria@example.com',
        'phone' => '317-555-0123',
        'status' => 'active',
    ]);
});

test('authenticated user can update their own contact', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->forUser($user)->create([
        'first_name' => 'James',
        'last_name' => 'Carter',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($user)->put(route('contacts.update', $contact), [
        'first_name' => 'Jamie',
        'last_name' => 'Carter',
        'email' => 'jamie@example.com',
        'phone' => '317-555-0456',
        'status' => 'active',
        'notes' => 'Now serving with the hospitality team.',
        'tag_ids' => [],
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contacts.edit', $contact));

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'user_id' => $user->id,
        'first_name' => 'Jamie',
        'email' => 'jamie@example.com',
        'status' => 'active',
    ]);
});

test('contact index only shows the signed-in users contacts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Contact::factory()->forUser($user)->create([
        'first_name' => 'Avery',
        'last_name' => 'Cole',
    ]);

    Contact::factory()->forUser($otherUser)->create([
        'first_name' => 'Harper',
        'last_name' => 'Nash',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts.data', 1)
            ->has('contacts.links')
            ->where('contacts.data.0.full_name', 'Avery Cole'),
        );
});

test('contact index includes paginator links for the pagination ui', function () {
    $user = User::factory()->create();

    Contact::factory()->count(21)->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('contacts.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('contacts.current_page', 2)
            ->where('contacts.last_page', 2)
            ->where('contacts.links.1.label', '1')
            ->where('contacts.links.2.label', '2')
            ->where('contacts.links.2.active', true),
        );
});

test('user cannot view another users contact edit page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = Contact::factory()->forUser($otherUser)->create();

    $this->actingAs($user)
        ->get(route('contacts.edit', $contact))
        ->assertNotFound();
});

test('user cannot update another users contact', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = Contact::factory()->forUser($otherUser)->create();

    $this->actingAs($user)
        ->put(route('contacts.update', $contact), [
            'first_name' => 'Blocked',
            'last_name' => 'Update',
            'email' => 'blocked@example.com',
            'phone' => '317-555-0000',
            'status' => 'active',
            'notes' => null,
            'tag_ids' => [],
        ])
        ->assertNotFound();

    expect($contact->fresh()->first_name)->not->toBe('Blocked');
});

test('user cannot attach another users tags to their contact', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $contact = Contact::factory()->forUser($user)->create();
    $foreignTag = Tag::factory()->forUser($otherUser)->create([
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    $this->actingAs($user)
        ->from(route('contacts.edit', $contact))
        ->put(route('contacts.update', $contact), [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'status' => $contact->status->value,
            'notes' => $contact->notes,
            'tag_ids' => [$foreignTag->id],
        ])
        ->assertSessionHasErrors('tag_ids.0')
        ->assertRedirect(route('contacts.edit', $contact));

    expect($contact->fresh()->tags)->toHaveCount(0);
});

test('search and filters only return the signed-in users contacts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $members = Tag::factory()->forUser($user)->create(['name' => 'Members', 'slug' => 'members']);
    $staff = Tag::factory()->forUser($user)->create(['name' => 'Staff', 'slug' => 'staff']);
    $otherUsersMembers = Tag::factory()->forUser($otherUser)->create(['name' => 'Members', 'slug' => 'members']);

    $matchingContact = Contact::factory()->forUser($user)->create([
        'first_name' => 'Grace',
        'last_name' => 'Lee',
        'email' => 'grace@example.com',
        'phone' => '317-555-0111',
        'status' => 'active',
    ]);
    $matchingContact->tags()->sync([$members->id]);

    $excludedByStatus = Contact::factory()->forUser($user)->create([
        'first_name' => 'Grace',
        'last_name' => 'Howard',
        'email' => 'grace.howard@example.com',
        'phone' => '317-555-0222',
        'status' => 'inactive',
    ]);
    $excludedByStatus->tags()->sync([$members->id]);

    $excludedByTag = Contact::factory()->forUser($user)->create([
        'first_name' => 'Grace',
        'last_name' => 'Miller',
        'email' => 'grace.miller@example.com',
        'phone' => '317-555-0333',
        'status' => 'active',
    ]);
    $excludedByTag->tags()->sync([$staff->id]);

    $otherUsersMatchingContact = Contact::factory()->forUser($otherUser)->create([
        'first_name' => 'Grace',
        'last_name' => 'Stone',
        'email' => 'grace.stone@example.com',
        'phone' => '317-555-0444',
        'status' => 'active',
    ]);
    $otherUsersMatchingContact->tags()->sync([$otherUsersMembers->id]);

    $this->actingAs($user)
        ->get(route('contacts.index', [
            'search' => 'Grace',
            'status' => 'active',
            'tag' => $members->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('filters.search', 'Grace')
            ->where('filters.status', 'active')
            ->where('filters.tag', $members->id)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.full_name', 'Grace Lee'),
        );
});

test('search can match the signed-in users tag names without leaking another users contacts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $volunteers = Tag::factory()->forUser($user)->create(['name' => 'Volunteers', 'slug' => 'volunteers']);
    $otherVolunteers = Tag::factory()->forUser($otherUser)->create([
        'name' => 'Volunteers',
        'slug' => 'volunteers',
    ]);

    $matchingContact = Contact::factory()->forUser($user)->create([
        'first_name' => 'Naomi',
        'last_name' => 'Reed',
    ]);
    $matchingContact->tags()->sync([$volunteers->id]);

    $otherUsersMatchingContact = Contact::factory()->forUser($otherUser)->create([
        'first_name' => 'Naomi',
        'last_name' => 'Cross',
    ]);
    $otherUsersMatchingContact->tags()->sync([$otherVolunteers->id]);

    $this->actingAs($user)
        ->get(route('contacts.index', ['search' => 'Volunteer']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.full_name', 'Naomi Reed'),
        );
});

test('foreign tag filters are ignored instead of broadening the users contact query', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedContact = Contact::factory()->forUser($user)->create([
        'first_name' => 'Mia',
        'last_name' => 'Parker',
    ]);

    $foreignTag = Tag::factory()->forUser($otherUser)->create([
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index', ['tag' => $foreignTag->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('filters.tag', null)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.full_name', $ownedContact->full_name),
        );
});

test('tag lists shown for forms and filters only contain the signed-in users tags', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userTag = Tag::factory()->forUser($user)->create([
        'name' => 'Members',
        'slug' => 'members',
    ]);
    Tag::factory()->forUser($otherUser)->create([
        'name' => 'Staff',
        'slug' => 'staff',
    ]);

    $contact = Contact::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('contacts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/create')
            ->has('tags', 1)
            ->where('tags.0.id', $userTag->id),
        );

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('tags', 1)
            ->where('tags.0.id', $userTag->id),
        );

    $this->actingAs($user)
        ->get(route('contacts.edit', $contact))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/edit')
            ->has('tags', 1)
            ->where('tags.0.id', $userTag->id),
        );
});
