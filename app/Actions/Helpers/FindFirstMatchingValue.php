<?php

namespace App\Actions\Helpers;

use Illuminate\Support\Collection;

class FindFirstMatchingValue
{
    /**
     * Get the first matching value from $needles that exists in $haystack.
     *
     * @param array|Collection $haystack
     * @param array|Collection $needles
     * @return mixed|null
     */
    public static function run(array|Collection $haystack, array|Collection $needles): mixed
    {
        $haystack = collect($haystack);
        return collect($needles)->first(fn($item) => $haystack->contains($item));
    }
}
