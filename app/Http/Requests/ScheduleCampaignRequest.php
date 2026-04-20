<?php

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ScheduleCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('schedule', $this->route('campaign')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * Get the validated scheduled date and time.
     */
    public function scheduledFor(): CarbonInterface
    {
        return Carbon::parse((string) $this->safe()->input('scheduled_for'));
    }
}
