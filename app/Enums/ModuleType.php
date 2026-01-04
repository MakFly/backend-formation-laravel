<?php

declare(strict_types=1);

namespace App\Enums;

enum ModuleType: string
{
    case VIDEO = 'video';
    case TEXT = 'text';
    case INTERACTIVE = 'interactive';
    case QUIZ = 'quiz';
    case ASSIGNMENT = 'assignment';
    case MIXED = 'mixed';
}
