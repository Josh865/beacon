<?php

namespace App\Http\Requests;

use App\Models\Contact;

class StoreContactRequest extends ContactRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->can('create', Contact::class);
    }
}
