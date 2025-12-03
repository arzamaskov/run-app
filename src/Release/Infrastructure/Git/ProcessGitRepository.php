<?php

declare(strict_types=1);

namespace RunTracker\Release\Infrastructure\Git;

use Exception;
use Illuminate\Support\Facades\File;
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

    public function getCommitsSinceTag(?string $tag): array
    {
        $range = $tag ? "{$tag}..HEAD" : 'HEAD';

        $process = new Process([
            'git',
            'log',
            $range,
            '--pretty=format:%s|||%h|||%an|||%ad',
            '--date=short',
        ]);
        $process->setWorkingDirectory($this->workingDirectory);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", $output) as $line) {
            [$message, $hash, $author, $date] = explode('|||', $line);
            $commits[] = compact('message', 'hash', 'author', 'date');
        }

        return $commits;
    }

    public function createTag(string $version, string $message): void
    {
        $process = new Process([
            'git',
            'tag',
            '-a',
            $version,
            '-m',
            $message,
        ]);
        $process->setWorkingDirectory($this->workingDirectory);
        $process->run();
    }

    public function commit(array $files, string $message): void
    {
        foreach ($files as $file) {
            if (File::exists(rtrim($this->workingDirectory, '/').DIRECTORY_SEPARATOR.$file)) {
                $process = new Process(['git', 'add', $file]);
                $process->setWorkingDirectory($this->workingDirectory);
                $process->run();
            }
        }

        $process = new Process([
            'git',
            'commit',
            '-m',
            $message,
        ]);
        $process->setWorkingDirectory($this->workingDirectory);
        $process->run();
    }

    public function isRepository(): bool
    {
        return is_dir(rtrim($this->workingDirectory, '/').DIRECTORY_SEPARATOR.'.git');
    }
}
