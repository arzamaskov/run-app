<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Command\PrepareRelease;

use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Shared\Application\Command\Command;

final readonly class PrepareReleaseCommand implements Command
{
    /**
     * @param  string|null  $version  опциональная версия релиза, если null, то рассчитывается на основе git тегов
     * @param  ReleaseType  $type  тип релиза, по умолчанию Minor
     * @param  bool  $createTag  создавать ли git тег
     * @param  bool  $commitChanges  коммитить ли изменения в git
     */
    public function __construct(
        public ?string $version = null,
        public ReleaseType $type = ReleaseType::MINOR,
        public bool $createTag = true,
        public bool $commitChanges = true
    ) {}
}
