<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsaleCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accountId'              => ['required', 'string'],
            'accountLogin'           => ['required', 'string'],
            'newOfferId'             => ['required', 'string'],
            'trialDays'              => ['sometimes', 'integer', 'min:0'],
            'serviceStartTimestamp'  => ['sometimes', 'nullable', 'string'],
            'qsTransactionId'        => ['required', 'string'],
            'billingTransactionId'   => ['required', 'uuid'],

            'newOfferName'  => ['sometimes', 'nullable', 'string'],
            'clientIp'      => ['sometimes', 'nullable', 'string'],
            'userInfo'      => ['sometimes', 'nullable', 'array'],
            'deviceInfo'    => ['sometimes', 'nullable', 'array'],
            'appInfo'       => ['sometimes', 'nullable', 'array'],
        ];
    }
}
