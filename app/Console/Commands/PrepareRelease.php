<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\File;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommand;
use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Shared\Application\Command\CommandBus;
use Symfony\Component\Process\Process;

class PrepareRelease extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:prepare
                            {version? : Version number (e.g., 1.2.3)}
                            {--type=minor : Release type: major, minor, patch}
                            {--no-tag : Do not create git tag}
                            {--no-commit : Do not commit changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare a new release: update version, generate changelog, create git tag';

    private string $changelogPath;

    private string $configPath;

    private string $version;

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

        $this->changelogPath = base_path('CHANGELOG.md');
        $this->configPath = config_path('app.php');

        if (! $this->isGitRepository()) {
            $this->error('Not a git repository');

            return 1;
        }

        $releaseType = ReleaseType::fromString($this->option('type'));
        $command = new PrepareReleaseCommand(
            version: $this->argument('version'),
            type: $releaseType,
            createTag: ! $this->option('no-tag'),
            commitChanges: ! $this->option('no-commit'),
        );

        $version = $this->commandBus->execute($command);
        $this->version = $version->toString();

        if (! $this->generateChangelog()) {
            return 1;
        }

        // Обновление версии в конфиге
        $this->updateVersionInConfig();

        // Обновление .env
        $this->updateEnvVersion();

        // Git операции
        if (! $this->option('no-commit')) {
            $this->commitChanges();
        }

        if (! $this->option('no-tag')) {
            $this->createGitTag();
        }

        $this->info('✅ Release prepared successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Review CHANGELOG.md');
        $this->line('  2. git push origin main');
        $this->line("  3. git push origin {$this->version}");

        return 0;
    }

    private function isGitRepository(): bool
    {
        return is_dir(base_path('.git'));
    }

    private function getLastTag(): ?string
    {
        $process = new Process(['git', 'describe', '--tags', '--abbrev=0']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    private function generateChangelog(): bool
    {
        $this->info('📝 Generating changelog...');

        $commits = $this->getCommitsSinceLastTag();

        if (empty($commits)) {
            $this->warn('No new commits since last tag');

            return true;
        }

        $changelogEntry = $this->buildChangelogEntry($commits);

        $this->prependToChangelog($changelogEntry);

        $this->info("Added {$this->version} to CHANGELOG.md");

        return true;
    }

    private function getCommitsSinceLastTag(): array
    {
        $lastTag = $this->getLastTag();

        $range = $lastTag ? "{$lastTag}..HEAD" : 'HEAD';

        $process = new Process([
            'git',
            'log',
            $range,
            '--pretty=format:%s|||%h|||%an|||%ad',
            '--date=short',
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());
        if (empty($output)) {
            return [];
        }

        $commits = [];
        foreach (explode("\n", $output) as $line) {
            [$message, $hash, $author, $date] = explode('|||', $line);
            $commits[] = compact('message', 'hash', 'author', 'date');
        }

        return $commits;
    }

    private function buildChangelogEntry(array $commits): string
    {
        $date = date('Y-m-d');
        $entry = "## [{$this->version}] - {$date}\n\n";

        $grouped = $this->groupCommitsByType($commits);

        foreach ($grouped as $type => $items) {
            if (! empty($items)) {
                $entry .= "### {$type}\n\n";
                foreach ($items as $commit) {
                    $entry .= "- {$commit['message']} ([{$commit['hash']}])\n";
                }
                $entry .= "\n";
            }
        }

        return $entry;
    }

    private function groupCommitsByType(array $commits): array
    {
        $grouped = [
            'Added' => [],
            'Changed' => [],
            'Fixed' => [],
            'Removed' => [],
            'Security' => [],
            'Other' => [],
        ];

        foreach ($commits as $commit) {
            $message = $commit['message'];

            if (preg_match('/^(feat|feature|add)(\(.*?\))?:/i', $message)) {
                $grouped['Added'][] = $commit;
            } elseif (preg_match('/^(fix|bug)(\(.*?\))?:/i', $message)) {
                $grouped['Fixed'][] = $commit;
            } elseif (preg_match('/^(change|update|refactor)(\(.*?\))?:/i', $message)) {
                $grouped['Changed'][] = $commit;
            } elseif (preg_match('/^(remove|delete)(\(.*?\))?:/i', $message)) {
                $grouped['Removed'][] = $commit;
            } elseif (preg_match('/^(security|sec)(\(.*?\))?:/i', $message)) {
                $grouped['Security'][] = $commit;
            } else {
                $grouped['Other'][] = $commit;
            }
        }

        return $grouped;
    }

    private function prependToChangelog(string $entry): void
    {
        if (! File::exists($this->changelogPath)) {
            $header = "# Changelog\n\n";
            $header .= "All notable changes to this project will be documented in this file.\n\n";
            $header .= "The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),\n";
            $header .= "and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).\n\n";
            File::put($this->changelogPath, $header);
        }

        $existing = File::get($this->changelogPath);

        // Найти место после заголовка
        $parts = preg_split('/^## /m', $existing, 2);

        if (count($parts) > 1) {
            $content = $parts[0].$entry.'## '.$parts[1];
        } else {
            $content = $existing."\n".$entry;
        }

        File::put($this->changelogPath, $content);
    }

    private function updateVersionInConfig(): void
    {
        $this->info('📝 Updating version in config...');

        if (! File::exists($this->configPath)) {
            $this->warn('config/app.php not found');

            return;
        }

        $content = File::get($this->configPath);

        // Ищем строку с version
        if (preg_match("/'version'\s*=>\s*env\('APP_VERSION',\s*'([^']+)'\)/", $content)) {
            $content = preg_replace(
                "/'version'\s*=>\s*env\('APP_VERSION',\s*'[^']+'\)/",
                "'version' => env('APP_VERSION', '{$this->version}')",
                $content
            );
        } else {
            // Добавляем после 'name' если не найдено
            $content = preg_replace(
                "/('name'\s*=>\s*env\('APP_NAME',\s*'[^']+'\),)/",
                "$1\n    'version' => env('APP_VERSION', '{$this->version}'),",
                $content
            );
        }

        File::put($this->configPath, $content);
    }

    private function updateEnvVersion(): void
    {
        $this->info('📝 Updating .env...');

        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('.env not found');

            return;
        }

        $content = File::get($envPath);

        if (preg_match('/^APP_VERSION=/m', $content)) {
            $content = preg_replace(
                '/^APP_VERSION=.*/m',
                "APP_VERSION={$this->version}",
                $content
            );
        } else {
            $content .= "\nAPP_VERSION={$this->version}\n";
        }

        File::put($envPath, $content);
    }

    private function commitChanges(): void
    {
        $this->info('📝 Committing changes...');

        if (! $this->checkGitConfig()) {
            $this->error('Git user not configured!');
            $this->line('Run: git config user.email "you@example.com"');
            $this->line('     git config user.name "Your Name"');

            return;
        }

        $files = ['CHANGELOG.md', 'config/app.php', '.env'];

        foreach ($files as $file) {
            if (File::exists(base_path($file))) {
                $process = new Process(['git', 'add', $file]);
                $process->setWorkingDirectory(base_path());
                $process->run();
            }
        }

        $process = new Process([
            'git',
            'commit',
            '-m',
            "chore: prepare release {$this->version}",
        ]);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if ($process->isSuccessful()) {
            $this->info('✅ Changes committed');
        } else {
            $this->warn('No changes to commit or commit failed');
        }
    }

    private function createGitTag(): void
    {
        $this->info('🏷️  Creating git tag...');

        $process = new Process([
            'git',
            'tag',
            '-a',
            $this->version,
            '-m',
            "Release {$this->version}",
        ]);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if ($process->isSuccessful()) {
            $this->info("✅ Tag {$this->version} created");
        } else {
            $this->error('Failed to create tag: '.$process->getErrorOutput());
        }
    }

    private function checkGitConfig(): bool
    {
        $emailProcess = new Process(['git', 'config', 'user.email']);
        $emailProcess->run();

        $nameProcess = new Process(['git', 'config', 'user.name']);
        $nameProcess->run();

        $hasConfig = $emailProcess->isSuccessful() &&
            $nameProcess->isSuccessful() &&
            ! empty(trim($emailProcess->getOutput())) &&
            ! empty(trim($nameProcess->getOutput()));

        // Если Git не настроен, попробовать настроить из .env
        if (! $hasConfig) {
            $hasConfig = $this->setupGitFromEnv();
        }

        return $hasConfig;
    }

    private function setupGitFromEnv(): bool
    {
        $name = config('app.git_user_name');
        $email = config('app.git_user_email');

        if (! $name || ! $email) {
            return false;
        }

        $this->info('⚙️  Configuring Git from .env...');

        // Настраиваем Git для текущего репозитория
        $nameProcess = new Process(['git', 'config', 'user.name', $name]);
        $nameProcess->setWorkingDirectory(base_path());
        $nameProcess->run();

        $emailProcess = new Process(['git', 'config', 'user.email', $email]);
        $emailProcess->setWorkingDirectory(base_path());
        $emailProcess->run();

        if ($nameProcess->isSuccessful() && $emailProcess->isSuccessful()) {
            $this->info("✅ Git configured: {$name} <{$email}>");

            return true;
        }

        return false;
    }
}
