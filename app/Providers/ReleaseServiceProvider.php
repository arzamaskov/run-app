<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommand;
use RunTracker\Release\Application\Command\PrepareRelease\PrepareReleaseCommandHandler;
use RunTracker\Release\Domain\Port\GitRepository;
use RunTracker\Release\Infrastructure\Git\ProcessGitRepository;
use RunTracker\Shared\Application\Command\CommandBus;

final class ReleaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            GitRepository::class,
            fn ($app) => new ProcessGitRepository(base_path())
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
