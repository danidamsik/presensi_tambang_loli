<?php

namespace App\Support;

final class PublicFileUrl
{
    public static function make(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return route('public-files.show', ['path' => ltrim($path, '/')], false);
    }
}
