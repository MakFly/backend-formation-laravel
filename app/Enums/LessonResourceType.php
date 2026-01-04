<?php

declare(strict_types=1);

namespace App\Enums;

enum LessonResourceType: string
{
    case PDF = 'pdf';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case DOCUMENT = 'document';
    case ARCHIVE = 'archive';
    case LINK = 'link';
    case CODE = 'code';
    case ATTACHMENT = 'attachment';
}
