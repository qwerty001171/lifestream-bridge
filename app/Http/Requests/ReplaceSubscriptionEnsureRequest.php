<?php

namespace App\Http\Requests;

class ReplaceSubscriptionEnsureRequest extends UpsaleEnsureRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'oldOfferId' => ['required', 'string'],
            'oldOfferName' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
