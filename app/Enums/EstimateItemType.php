<?php

namespace App\Enums;

enum EstimateItemType: string
{
    case Part = 'part';
    case Labor = 'labor';
    case Tire = 'tire';
    case Subcontract = 'subcontract';
    case Fee = 'fee';
}
