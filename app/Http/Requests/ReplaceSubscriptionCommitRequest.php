<?php

namespace App\Http\Requests;

class ReplaceSubscriptionCommitRequest extends UpsaleCommitRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'oldOfferId'   => ['required', 'string'],
            'oldOfferName' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
