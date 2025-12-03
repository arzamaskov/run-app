<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Port;

interface FileRepository
{
    /**
     * Обновляет версию в config/app.php
     */
    public function updateVersionInConfig(string $version): void;

    /**
     * Обновляет версию в .env
     */
    public function updateVersionInEnv(string $version): void;

    /**
     * Добавляет changelog entry в начало файла CHANGELOG.md
     */
    public function prependToChangelog(string $content): void;
}
