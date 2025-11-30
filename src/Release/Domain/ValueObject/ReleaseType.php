<?php

declare(strict_types=1);

namespace RunTracker\Release\Domain\ValueObject;

use InvalidArgumentException;

enum ReleaseType: string
{
    case MAJOR = 'major';
    case MINOR = 'minor';
    case PATCH = 'patch';

    public static function fromString(string $type): self
    {
        return match ($type) {
            'major' => self::MAJOR,
            'minor' => self::MINOR,
            'patch' => self::PATCH,
            default => throw new InvalidArgumentException("Invalid release type: {$type}")
        };
    }
}
