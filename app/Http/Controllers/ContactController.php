<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        $user = $request->user();
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'tag' => $this->filterTagId($request),
        ];

        $contacts = $user->contacts()
            ->with(['tags:id,name,slug'])
            ->search($filters['search'])
            ->withStatus($filters['status'])
            ->withTag($filters['tag'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'full_name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'status' => $contact->status->value,
                'notes' => $contact->notes,
                'tags' => $contact->tags
                    ->sortBy('name')
                    ->values()
                    ->map(fn (Tag $tag): array => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ]),
            ]);

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'tags' => $this->tagOptions($user),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(): Response
    {
        $this->authorize('create', Contact::class);

        return Inertia::render('contacts/create', [
            'contact' => [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'status' => 'active',
                'notes' => '',
                'tag_ids' => [],
            ],
            'tags' => $this->tagOptions(request()->user()),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Store a newly created contact in storage.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = $request->user()->contacts()->create($request->contactData());
        $contact->tags()->sync($request->tagIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.index');
    }

    /**
     * Show the form for editing the specified contact.
     */
    public function edit(Contact $contact): Response
    {
        $this->authorize('view', $contact);

        $contact->load(['tags:id,name,slug']);

        return Inertia::render('contacts/edit', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email ?? '',
                'phone' => $contact->phone,
                'status' => $contact->status->value,
                'notes' => $contact->notes ?? '',
                'tag_ids' => $contact->tags->pluck('id')->all(),
            ],
            'tags' => $this->tagOptions(request()->user()),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Update the specified contact in storage.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $contact->update($request->contactData());
        $contact->tags()->sync($request->tagIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('contacts.edit', $contact);
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
     * Get the supported status options.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    /**
     * Get the selected tag filter only when it belongs to the authenticated user.
     */
    protected function filterTagId(Request $request): ?int
    {
        $tagId = $request->integer('tag');

        if ($tagId === 0) {
            return null;
        }

        return $request->user()->tags()->whereKey($tagId)->exists() ? $tagId : null;
    }
}
