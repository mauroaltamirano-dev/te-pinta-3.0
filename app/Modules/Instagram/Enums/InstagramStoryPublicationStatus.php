<?php

namespace App\Modules\Instagram\Enums;

enum InstagramStoryPublicationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
