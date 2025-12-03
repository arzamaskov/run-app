<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Service;

use RunTracker\Release\Domain\Port\GitRepository;
use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Release\Domain\ValueObject\Version;

final readonly class VersionCalculator
{
    public function __construct(private GitRepository $repository) {}

    /**
     * Вычисляет следующую версию релиза.
     *
     * Если версия указана явно - валидирует и возвращает её.
     * Если не указана - получает последний тег и инкрементирует на основе типа.
     *
     * @param  string|null  $explicitVersion  Версия, указанная явно (например, "1.5.0")
     * @param  ReleaseType  $type  Тип релиза для автоматического инкремента
     * @return Version Следующая версия
     */
    public function calculateNextVersion(?string $explicitVersion, ReleaseType $type): Version
    {
        if ($explicitVersion !== null) {
            return Version::fromString($explicitVersion);
        }

        $lastVersion = $this->getLastVersion();
        if ($lastVersion === null) {
            return Version::initial();
        }

        return $lastVersion->increment($type);
    }

    /**
     * Получает последнюю версию из git тегов.
     *
     * @return Version|null Последняя версия или null, если тегов нет
     */
    private function getLastVersion(): ?Version
    {
        $lastTag = $this->repository->getLastTag();

        return $lastTag !== null ? Version::fromString($lastTag) : null;
    }
}
