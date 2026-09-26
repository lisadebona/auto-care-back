<?php

namespace App\Enums;

enum EstimateOrderStatus: string
{
    case Estimate = 'estimate';
    case Invoice = 'invoice';
}
