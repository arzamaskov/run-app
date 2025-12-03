<?php

declare(strict_types=1);

namespace RunTracker\Release\Domain\Port;

interface GitRepository
{
    /**
     * Получает последний git тег.
     *
     * @return string|null Тег в формате "v1.2.3" или null, если тегов нет
     */
    public function getLastTag(): ?string;

    public function getCommitsSinceTag(?string $tag): array;

    public function createTag(string $version, string $message): void;

    public function commit(array $files, string $message): void;

    public function isRepository(): bool;
}
