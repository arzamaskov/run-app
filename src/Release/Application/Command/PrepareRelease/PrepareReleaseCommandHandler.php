<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Command\PrepareRelease;

use RunTracker\Release\Application\Service\VersionCalculator;
use RunTracker\Release\Domain\ValueObject\Version;
use RunTracker\Shared\Application\Command\Command;
use RunTracker\Shared\Application\Command\CommandHandler;

final readonly class PrepareReleaseCommandHandler implements CommandHandler
{
    public function __construct(private VersionCalculator $versionCalculator) {}

    public function handle(Command $command): Version
    {
        assert($command instanceof PrepareReleaseCommand);

        return $this->versionCalculator->calculateNextVersion($command->version, $command->type);
    }
}
