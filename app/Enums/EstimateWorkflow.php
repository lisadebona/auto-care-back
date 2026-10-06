<?php

namespace App\Enums;

enum EstimateWorkflow: string
{
    case Estimates = 'estimates';
    case DroppedOff = 'dropped_off';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Invoices = 'invoices';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Estimates => 'Estimates',
            self::DroppedOff => 'Dropped Off',
            self::InProgress => 'In Progress / Repair Order',
            self::Completed => 'Invoiced / Complete',
            self::Invoices => 'Invoices',
            self::Cancelled => 'Cancelled',
        };
    }
}
