<?php

namespace App\Http\Requests\Tenants\Webhooks;

use App\Services\WebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $webhookService = app(WebhookService::class);

        return [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', $webhookService->getAvailableEvents()),
            'secret' => 'nullable|string|max:255',
            'headers' => 'nullable|array',
            'max_retries' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:5|max:3600',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $headers = $this->input('headers');

            if ($headers && is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (!is_string($value)) {
                        $validator->errors()->add('headers', "Header value for '{$key}' must be a string.");
                    } elseif (strlen($value) > 1000) {
                        $validator->errors()->add('headers', "Header value for '{$key}' exceeds maximum length of 1000 characters.");
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The webhook name is required.',
            'url.required' => 'The webhook URL is required.',
            'url.url' => 'The webhook URL must be a valid URL.',
            'events.required' => 'At least one event must be selected.',
            'events.*.in' => 'One or more selected events are invalid.',
            'max_retries.min' => 'Maximum retries must be at least 0.',
            'max_retries.max' => 'Maximum retries cannot exceed 10.',
            'retry_delay.min' => 'Retry delay must be at least 5 seconds.',
            'retry_delay.max' => 'Retry delay cannot exceed 3600 seconds (1 hour).',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'events.*' => 'event',
        ];
    }
}
