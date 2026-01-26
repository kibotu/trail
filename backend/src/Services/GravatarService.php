<?php

declare(strict_types=1);

namespace Trail\Services;

class GravatarService
{
    public static function generateHash(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    public static function generateUrl(string $hash, int $size = 80, string $default = 'identicon'): string
    {
        return sprintf(
            'https://www.gravatar.com/avatar/%s?s=%d&d=%s',
            $hash,
            $size,
            urlencode($default)
        );
    }

    public static function generateUrlFromEmail(string $email, int $size = 80, string $default = 'identicon'): string
    {
        $hash = self::generateHash($email);
        return self::generateUrl($hash, $size, $default);
    }
}
