<?php

namespace App\Http\Requests;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CampaignRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'message_body' => ['required', 'string', 'max:2000'],
            'status' => ['required', Rule::in([CampaignStatus::Draft->value])],
            'audience_type' => ['required', Rule::in(CampaignAudienceType::implementedValues())],
            'tag_ids' => [
                Rule::requiredIf(fn (): bool => $this->audienceType() === CampaignAudienceType::TagSelection),
                'array',
                Rule::when(
                    $this->audienceType() === CampaignAudienceType::TagSelection,
                    ['min:1'],
                ),
            ],
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
        $tagIds = $this->input('tag_ids', []);

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'message_body' => trim((string) $this->input('message_body')),
            'status' => CampaignStatus::Draft->value,
            'tag_ids' => collect(is_array($tagIds) ? $tagIds : [])
                ->filter(fn (mixed $tagId): bool => filled($tagId))
                ->map(fn (mixed $tagId): int => (int) $tagId)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get the validated campaign attributes.
     *
     * @return array{
     *     name: string,
     *     message_body: string,
     *     status: string,
     *     audience_type: string,
     *     scheduled_for: null,
     *     sent_at: null
     * }
     */
    public function campaignData(): array
    {
        /** @var array{
         *     name: string,
         *     message_body: string,
         *     status: string,
         *     audience_type: string
         * } $data
         */
        $data = $this->safe()->only([
            'name',
            'message_body',
            'status',
            'audience_type',
        ]);

        return [
            ...$data,
            'scheduled_for' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Get the validated audience type enum instance.
     */
    public function audienceType(): CampaignAudienceType
    {
        return CampaignAudienceType::from(
            (string) $this->input('audience_type', CampaignAudienceType::AllContacts->value),
        );
    }

    /**
     * Get the validated tag IDs when tag selection is active.
     *
     * @return list<int>
     */
    public function tagIds(): array
    {
        if ($this->audienceType() !== CampaignAudienceType::TagSelection) {
            return [];
        }

        /** @var list<int> $tagIds */
        $tagIds = $this->safe()->collect('tag_ids')->map(fn (mixed $tagId): int => (int) $tagId)->all();

        return $tagIds;
    }
}
