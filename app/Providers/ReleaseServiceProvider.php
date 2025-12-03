<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommand;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommandHandler;
use RunTracker\Release\Application\Port\ChangelogGenerator;
use RunTracker\Release\Application\Port\FileRepository;
use RunTracker\Release\Domain\Port\GitRepository;
use RunTracker\Release\Infrastructure\Changelog\MarkdownChangelogGenerator;
use RunTracker\Release\Infrastructure\File\LaravelFileRepository;
use RunTracker\Release\Infrastructure\Git\ProcessGitRepository;
use RunTracker\Shared\Application\Command\CommandBus;

final class ReleaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // GitRepository
        $this->app->singleton(
            GitRepository::class,
            fn ($app) => new ProcessGitRepository(base_path())
        );

        // ChangelogGenerator
        $this->app->singleton(
            ChangelogGenerator::class,
            MarkdownChangelogGenerator::class
        );

        // FileRepository
        $this->app->singleton(
            FileRepository::class,
            fn ($app) => new LaravelFileRepository(
                config_path('app.php'),
                base_path('.env'),
                base_path('CHANGELOG.md')
            )
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $commandBus = $this->app->make(CommandBus::class);
        $commandBus->register(
            PrepareReleaseCommand::class,
            PrepareReleaseCommandHandler::class
        );
    }
}
