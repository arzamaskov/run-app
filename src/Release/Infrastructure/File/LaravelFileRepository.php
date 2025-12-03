<?php

declare(strict_types=1);

namespace RunTracker\Release\Infrastructure\File;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use RuntimeException;
use RunTracker\Release\Application\Port\FileRepository;

final readonly class LaravelFileRepository implements FileRepository
{
    public function __construct(
        private string $configPath,
        private string $envPath,
        private string $changelogPath
    ) {}

    /**
     * @throws FileNotFoundException
     */
    public function updateVersionInConfig(string $version): void
    {
        $version = ltrim($version, 'vV');

        if (! File::exists($this->configPath)) {
            throw new RuntimeException("Config file not found: {$this->configPath}");
        }

        $content = File::get($this->configPath);

        // Ищем строку с version
        if (preg_match("/'version'\s*=>\s*env\('APP_VERSION',\s*'([^']+)'\)/", $content)) {
            $content = preg_replace(
                "/'version'\s*=>\s*env\('APP_VERSION',\s*'[^']+'\)/",
                "'version' => env('APP_VERSION', '{$version}')",
                $content
            );
        } else {
            // Добавляем после 'name' если не найдено
            $content = preg_replace(
                "/('name'\s*=>\s*env\('APP_NAME',\s*'[^']+'\),)/",
                "$1\n    'version' => env('APP_VERSION', '{$version}'),",
                $content
            );
        }

        if (File::put($this->configPath, $content) === false) {
            throw new RuntimeException("Failed to write config file: {$this->configPath}");
        }
    }

    public function updateVersionInEnv(string $version): void
    {
        $version = ltrim($version, 'vV');

        if (! File::exists($this->envPath)) {
            throw new RuntimeException("Env file not found: {$this->envPath}");
        }

        $content = File::get($this->envPath);

        if (preg_match('/^APP_VERSION=/m', $content)) {
            $content = preg_replace(
                '/^APP_VERSION=.*/m',
                "APP_VERSION={$version}",
                $content
            );
        } else {
            $content .= "\nAPP_VERSION={$version}\n";
        }

        if (File::put($this->envPath, $content) === false) {
            throw new RuntimeException("Failed to write env file: {$this->envPath}");
        }
    }

    public function prependToChangelog(string $content): void
    {
        if (! File::exists($this->changelogPath)) {
            $header = "# Changelog\n\n";
            $header .= "All notable changes to this project will be documented in this file.\n\n";
            $header .= "The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),\n";
            $header .= "and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).\n\n";

            if (File::put($this->changelogPath, $header) === false) {
                throw new RuntimeException("Failed to create changelog file: {$this->changelogPath}");
            }
        }

        $existing = File::get($this->changelogPath);

        // Найти место после заголовка
        $parts = preg_split('/^## /m', $existing, 2);

        if (count($parts) > 1) {
            $newContent = $parts[0].$content.'## '.$parts[1];
        } else {
            $newContent = $existing."\n".$content;
        }

        if (File::put($this->changelogPath, $newContent) === false) {
            throw new RuntimeException("Failed to write changelog file: {$this->changelogPath}");
        }
    }
}
