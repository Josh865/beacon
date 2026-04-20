<?php

namespace App\Http\Requests;

use App\Models\Campaign;

class StoreCampaignRequest extends CampaignRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campaign::class) ?? false;
    }
}
