<?php

namespace App\Http\Requests;

use App\Enums\CampaignAudienceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCampaignAudienceRequest extends FormRequest
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
            'tag_ids' => collect(is_array($tagIds) ? $tagIds : [])
                ->filter(fn (mixed $tagId): bool => filled($tagId))
                ->map(fn (mixed $tagId): int => (int) $tagId)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get the validated audience type enum instance.
     */
    public function audienceType(): CampaignAudienceType
    {
        return CampaignAudienceType::from((string) $this->input('audience_type'));
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
