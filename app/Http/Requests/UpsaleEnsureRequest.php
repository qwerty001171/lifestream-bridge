<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsaleEnsureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accountId'       => ['required', 'string', 'max:255'],
            'accountLogin'    => ['required', 'string', 'max:255'],
            'newOfferId'      => ['required', 'string', 'max:255'],
            'trialDays'       => ['sometimes', 'integer', 'min:0'],
            'qsTransactionId' => ['required', 'string', 'max:255'],

            'newOfferName'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'clientIp'                  => ['sometimes', 'nullable', 'string', 'max:45'],
            'userInfo'                  => ['sometimes', 'nullable', 'array'],
            'deviceInfo'                => ['sometimes', 'nullable', 'array'],
            'appInfo'                   => ['sometimes', 'nullable', 'array'],
        ];
    }
}
