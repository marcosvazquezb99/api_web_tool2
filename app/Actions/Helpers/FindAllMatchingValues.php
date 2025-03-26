<?php

namespace App\Actions\Helpers;

use Illuminate\Support\Collection;

class FindAllMatchingValues
{
    /**
     * Get all matching values from $needles that exist in $haystack.
     *
     * @param array|Collection $haystack
     * @param array|Collection $needles
     * @return array
     */
    public static function run(array|Collection $haystack, array|Collection $needles): array
    {
        $haystack = collect($haystack);

        return collect($needles)
            ->filter(fn($item) => $haystack->contains($item))
            ->values()
            ->all();
    }
}
