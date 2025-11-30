<?php

declare(strict_types=1);

namespace RunTracker\Release\Domain\Entity;

use DateTimeImmutable;
use RunTracker\Release\Domain\ValueObject\Changelog;
use RunTracker\Release\Domain\ValueObject\Version;

final readonly class Release
{
    public function __construct(
        public Version $version,
        public Changelog $changelog,
        public DateTimeImmutable $createdAt
    ) {}
}
