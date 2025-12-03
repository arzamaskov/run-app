<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use RuntimeException;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommand;
use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Shared\Application\Command\CommandBus;

class PrepareRelease extends Command
{
    protected $signature = 'release:prepare
                            {version? : Version number (e.g., 1.2.3)}
                            {--type=minor : Release type: major, minor, patch}
                            {--no-tag : Do not create git tag}
                            {--no-commit : Do not commit changes}';

    protected $description = 'Prepare a new release: update version, generate changelog, create git tag';

    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws BindingResolutionException
     */
    public function handle(): int
    {
        $this->info('Starting release preparation...');

        try {
            $releaseType = ReleaseType::fromString($this->option('type'));

            $command = new PrepareReleaseCommand(
                version: $this->argument('version'),
                type: $releaseType,
                createTag: ! $this->option('no-tag'),
                commitChanges: ! $this->option('no-commit'),
            );

            $version = $this->commandBus->execute($command);
            $versionString = $version->toString();

            $this->info('✅ Release prepared successfully!');
            $this->newLine();
            $this->line('Next steps:');
            $this->line('  1. Review CHANGELOG.md');
            $this->line('  2. git push origin main');
            $this->line("  3. git push origin {$versionString}");

            return 0;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
