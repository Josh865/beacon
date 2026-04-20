<?php

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'notes' => ['nullable', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('tags', 'id')->where(
                    fn (Builder $query): Builder => $query->where('user_id', $this->user()->id),
                ),
            ],
        ];
    }

    /**
     * Normalize incoming values before validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $tagIds = $this->input('tag_ids', []);

        $this->merge([
            'first_name' => $this->normalizeString($this->input('first_name')),
            'last_name' => $this->normalizeString($this->input('last_name')),
            'email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'phone' => trim((string) $this->input('phone')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'tag_ids' => collect(is_array($tagIds) ? $tagIds : [])
                ->filter(fn (mixed $tagId): bool => filled($tagId))
                ->map(fn (mixed $tagId): int => (int) $tagId)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get the validated contact attributes.
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     email: ?string,
     *     phone: string,
     *     status: string,
     *     notes: ?string
     * }
     */
    public function contactData(): array
    {
        /** @var array{
         *     first_name: string,
         *     last_name: string,
         *     email: ?string,
         *     phone: string,
         *     status: string,
         *     notes: ?string
         * } $data
         */
        $data = $this->safe()->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'status',
            'notes',
        ]);

        return $data;
    }

    /**
     * Get the validated tag IDs.
     *
     * @return list<int>
     */
    public function tagIds(): array
    {
        /** @var list<int> $tagIds */
        $tagIds = $this->safe()->collect('tag_ids')->map(fn (mixed $tagId): int => (int) $tagId)->all();

        return $tagIds;
    }

    /**
     * Normalize a required string value.
     */
    protected function normalizeString(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Normalize a nullable string value.
     */
    protected function normalizeNullableString(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
