<?php

namespace App\Enums;

enum FeeType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
