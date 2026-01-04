<?php

declare(strict_types=1);

namespace App\Enums;

enum LessonProgressStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
