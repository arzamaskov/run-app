<?php

declare(strict_types=1);

namespace RunTracker\Release\Application\Port;

use RunTracker\Release\Domain\ValueObject\Version;

interface ChangelogGenerator
{
    /**
     * Генерирует changelog entry из коммитов.
     *
     * @param  array  $commits  Массив коммитов ['message' => ..., 'hash' => ..., ...]
     * @param  Version  $version  Версия релиза
     * @return string Markdown строка changelog entry
     */
    public function generate(array $commits, Version $version): string;
}
