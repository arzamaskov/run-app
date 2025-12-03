<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Command\PrepareRelease;

use RuntimeException;
use RunTracker\Release\Application\Port\ChangelogGenerator;
use RunTracker\Release\Application\Port\FileRepository;
use RunTracker\Release\Application\Service\VersionCalculator;
use RunTracker\Release\Domain\Port\GitRepository;
use RunTracker\Release\Domain\ValueObject\Version;
use RunTracker\Shared\Application\Command\Command;
use RunTracker\Shared\Application\Command\CommandHandler;

final readonly class PrepareReleaseCommandHandler implements CommandHandler
{
    public function __construct(
        private VersionCalculator $versionCalculator,
        private GitRepository $gitRepository,
        private ChangelogGenerator $changelogGenerator,
        private FileRepository $fileRepository
    ) {}

    public function handle(Command $command): Version
    {
        assert($command instanceof PrepareReleaseCommand);

        // 1. Проверить, что это git репозиторий
        if (! $this->gitRepository->isRepository()) {
            throw new RuntimeException('Not a git repository');
        }

        // 2. Рассчитать версию
        $version = $this->versionCalculator->calculateNextVersion(
            $command->version,
            $command->type
        );

        // 3. Получить коммиты для changelog
        $lastTag = $this->gitRepository->getLastTag();
        $commits = $this->gitRepository->getCommitsSinceTag($lastTag);

        // 4. Сгенерировать changelog (если есть коммиты)
        if (! empty($commits)) {
            $changelogEntry = $this->changelogGenerator->generate($commits, $version);
            $this->fileRepository->prependToChangelog($changelogEntry);
        }

        // 5. Обновить версию в файлах
        $versionString = $version->toString();
        $this->fileRepository->updateVersionInConfig($versionString);
        $this->fileRepository->updateVersionInEnv($versionString);

        // 6. Коммитить изменения (если нужно)
        if ($command->commitChanges) {
            $this->gitRepository->commit(
                ['CHANGELOG.md', 'config/app.php', '.env'],
                "chore: prepare release {$versionString}"
            );
        }

        // 7. Создать тег (если нужно)
        if ($command->createTag) {
            $this->gitRepository->createTag($versionString, "Release {$versionString}");
        }

        return $version;
    }
}
