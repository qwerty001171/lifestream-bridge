<?php

namespace App\Http\Requests;

class ReplaceSubscriptionEnsureRequest extends UpsaleEnsureRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'oldOfferId'   => ['required', 'string', 'max:255'],
            'oldOfferName' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
