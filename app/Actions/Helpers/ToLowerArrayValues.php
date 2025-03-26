<?php

namespace App\Actions\Helpers;

use Illuminate\Support\Collection;

class ToLowerArrayValues
{
    /**
     * Convert all string values in the given array or collection to lowercase.
     *
     * @param array|Collection $items
     * @return array
     */
    public static function run(array|Collection $items): array
    {
        return collect($items)
            ->map(function ($item) {
                return is_string($item) ? mb_strtolower($item) : $item;
            })
            ->all();
    }
}
