<?php

namespace App\Http\Requests;

use App\Models\Contact;

class UpdateContactRequest extends ContactRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            && parent::authorize()
            && $this->user()->can('update', $contact);
    }
}
