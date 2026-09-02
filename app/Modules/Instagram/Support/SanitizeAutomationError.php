<?php

namespace App\Modules\Instagram\Support;

use Illuminate\Support\Str;

final class SanitizeAutomationError
{
    public static function execute(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? 'Error externo de publicación.';
        $message = preg_replace('/(?i)(authorization|access[_-]?token|bearer)(\s*[:=]?\s*)[^\s,;]+/', '$1$2[redacted]', $message) ?? $message;
        $message = preg_replace('/(?i)([?&](?:signature|access[_-]?token|token|secret|key)=)[^&\s]+/', '$1[redacted]', $message) ?? $message;

        return Str::limit(trim($message), 2000, '…');
    }
}
