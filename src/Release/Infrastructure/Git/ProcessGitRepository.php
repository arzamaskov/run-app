<?php

declare(strict_types=1);

namespace RunTracker\Release\Infrastructure\Git;

use Exception;
use RunTracker\Release\Domain\Port\GitRepository;
use Symfony\Component\Process\Process;

final readonly class ProcessGitRepository implements GitRepository
{
    public function __construct(private string $workingDirectory) {}

    public function getLastTag(): ?string
    {
        $process = new Process(['git', 'describe', '--tags', '--abbrev=0']);
        try {
            $process->setWorkingDirectory($this->workingDirectory);
            $process->run();
        } catch (Exception $e) {
            // @todo: добавить логирование
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output !== '' ? $output : null;
    }
}
