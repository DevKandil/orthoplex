<?php

namespace App\Rules\Central;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotReservedSubdomain implements ValidationRule
{
    /**
     * List of reserved subdomains.
     *
     * @var array
     */
    protected $reserved = [
        'www', 'admin', 'mail', 'api', 'test', 'localhost', 'secure', 'blog', 'support',
    ];

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Convert the subdomain to lowercase for case-insensitive comparison
        $subdomain = strtolower($value);

        // Check if the subdomain is in the reserved list
        if (in_array($subdomain, $this->reserved)) {
            $fail("The :attribute '$value' is a reserved word and cannot be used.");
        }
    }
}
