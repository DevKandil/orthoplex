<?php

namespace App\Rules\Central;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Stancl\Tenancy\Database\Models\Domain;

class UniqueTenantDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Convert subdomain to full domain format
        $domain = Str::slug(str_replace(' ', '', $value), '_') . '.' . config('app.domain');

        // Check if the domain already exists in the domains table
        if (Domain::where('domain', $domain)->exists()) {
            $fail(__('This subdomain is already occupied by another tenant.'));
        }
    }
}
