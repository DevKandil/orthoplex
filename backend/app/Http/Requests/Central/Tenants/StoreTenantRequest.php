<?php

namespace App\Http\Requests\Central\Tenants;

use App\Rules\Central\NotReservedSubdomain;
use App\Rules\Central\UniqueTenantDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'manager_name' => ['required', 'string', 'max:50'],
            'manager_email' => ['required', 'email', 'max:50'],
            'manager_password' => ['required', 'string', 'min:8', 'max:50'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha',
                new UniqueTenantDomain,
                new NotReservedSubdomain,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('The company name is required.'),
            'name.string' => __('The company name must be a string.'),
            'name.max' => __('The company name must not exceed 50 characters.'),

            'manager_name.required' => __('The manager\'s name is required.'),
            'manager_name.string' => __('The manager\'s name must be a string.'),
            'manager_name.max' => __('The manager\'s name must not exceed 50 characters.'),
            
            'manager_email.required' => __('The owner\'s email is required.'),
            'manager_email.email' => __('The owner\'s email must be a valid email address.'),
            'manager_email.max' => __('The owner\'s email must not exceed 50 characters.'),

            'manager_password.required' => __('The manager\'s password is required.'),
            'manager_password.string' => __('The manager\'s password must be a string.'),
            'manager_password.min' => __('The manager\'s password must be at least 8 characters.'),
            'manager_password.max' => __('The manager\'s password must not exceed 50 characters.'),

            'subdomain.required' => __('The subdomain is required.'),
            'subdomain.string' => __('The subdomain must be a string.'),
            'subdomain.min' => __('The subdomain must be at least 3 characters.'),
            'subdomain.max' => __('The subdomain must not exceed 50 characters.'),
            'subdomain.alpha' => __('The subdomain must contain only alphabetic characters.'),
            'subdomain.unique' => __('The subdomain has already been taken.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Company Name'),
            'manager_name' => __('Manager Name'),
            'manager_email' => __('Manager Email'),
            'manager_password' => __('Manager Password'),
            'subdomain' => __('Subdomain'),
        ];
    }
}
