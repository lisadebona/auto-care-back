<?php

namespace App\Support;

use ResourceBundle;

class Countries
{
    /**
     * Sorted English country display names from ICU data.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $bundle = ResourceBundle::create('en', 'ICUDATA-region', true);
        $countries = $bundle?->get('Countries');

        if ($countries === null) {
            return ['United States'];
        }

        $names = [];

        foreach ($countries as $code => $name) {
            if (! is_string($code) || strlen($code) !== 2 || ! ctype_alpha($code) || ! is_string($name)) {
                continue;
            }

            $names[] = $name;
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
