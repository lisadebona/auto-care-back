<?php

namespace App\Enums;

enum EstimateWorkflow: string
{
    case Estimates = 'estimates';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
