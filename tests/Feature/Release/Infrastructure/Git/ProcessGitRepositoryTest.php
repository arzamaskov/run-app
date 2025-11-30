<?php

declare(strict_types=1);

namespace Tests\Feature\Release\Infrastructure\Git;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RunTracker\Release\Infrastructure\Git\ProcessGitRepository;
use Symfony\Component\Process\Process;

class ProcessGitRepositoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём временную директорию для тестов
        $this->tempDir = sys_get_temp_dir().'/git-test-'.uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Удаляем временную директорию
        if (is_dir($this->tempDir)) {
            $this->deleteDirectory($this->tempDir);
        }
    }

    #[Test]
    public function it_returns_last_tag_when_tags_exist(): void
    {
        $this->initGitRepo();
        $this->createCommit('Initial commit');
        $this->createTag('v1.0.0');
        $this->createCommit('Second commit');
        $this->createTag('v1.1.0');

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertSame('v1.1.0', $lastTag);
    }

    #[Test]
    public function it_returns_null_when_no_tags_exist(): void
    {
        $this->initGitRepo();
        $this->createCommit('Initial commit');

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertNull($lastTag);
    }

    #[Test]
    public function it_returns_null_when_not_a_git_repository(): void
    {
        // Не инициализируем git репозиторий
        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertNull($lastTag);
    }

    #[Test]
    public function it_returns_only_tag_name_without_extra_whitespace(): void
    {
        $this->initGitRepo();
        $this->createCommit('Initial commit');
        $this->createTag('v2.3.4');

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertSame('v2.3.4', $lastTag);
        $this->assertStringNotContainsString("\n", $lastTag);
        $this->assertStringNotContainsString(' ', $lastTag);
    }

    #[Test]
    public function it_returns_most_recent_tag_when_multiple_tags_exist(): void
    {
        $this->initGitRepo();
        $this->createCommit('Commit 1');
        $this->createTag('v0.1.0');

        $this->createCommit('Commit 2');
        $this->createTag('v0.2.0');

        $this->createCommit('Commit 3');
        $this->createTag('v1.0.0');

        $this->createCommit('Commit 4');
        $this->createTag('v1.5.0');

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertSame('v1.5.0', $lastTag);
    }

    #[Test]
    public function it_handles_tags_without_v_prefix(): void
    {
        $this->initGitRepo();
        $this->createCommit('Initial commit');
        $this->createTag('1.0.0');

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertSame('1.0.0', $lastTag);
    }

    #[Test]
    public function it_handles_annotated_and_lightweight_tags(): void
    {
        $this->initGitRepo();
        $this->createCommit('Commit 1');
        $this->createTag('v1.0.0', true); // Annotated tag

        $this->createCommit('Commit 2');
        $this->createTag('v1.1.0', false); // Lightweight tag

        $repository = new ProcessGitRepository($this->tempDir);
        $lastTag = $repository->getLastTag();

        $this->assertSame('v1.1.0', $lastTag);
    }

    #[Test]
    public function it_returns_null_when_directory_does_not_exist(): void
    {
        $nonExistentDir = '/path/that/does/not/exist/at/all';

        $repository = new ProcessGitRepository($nonExistentDir);
        $lastTag = $repository->getLastTag();

        $this->assertNull($lastTag);
    }

    #[Test]
    public function it_works_with_different_working_directories(): void
    {
        // Первый репозиторий
        $dir1 = $this->tempDir.'/repo1';
        mkdir($dir1);
        $this->initGitRepo($dir1);
        $this->createCommit('Commit in repo1', $dir1);
        $this->createTag('v1.0.0', false, $dir1);

        // Второй репозиторий
        $dir2 = $this->tempDir.'/repo2';
        mkdir($dir2);
        $this->initGitRepo($dir2);
        $this->createCommit('Commit in repo2', $dir2);
        $this->createTag('v2.0.0', false, $dir2);

        $repo1 = new ProcessGitRepository($dir1);
        $repo2 = new ProcessGitRepository($dir2);

        $this->assertSame('v1.0.0', $repo1->getLastTag());
        $this->assertSame('v2.0.0', $repo2->getLastTag());
    }

    // Helper methods
    private function initGitRepo(?string $dir = null): void
    {
        $dir = $dir ?? $this->tempDir;

        $process = new Process(['git', 'init']);
        $process->setWorkingDirectory($dir);
        $process->run();

        // Настройка git config для тестов
        $this->runGitCommand(['config', 'user.email', 'test@example.com'], $dir);
        $this->runGitCommand(['config', 'user.name', 'Test User'], $dir);
    }

    private function createCommit(string $message, ?string $dir = null): void
    {
        $dir = $dir ?? $this->tempDir;

        // Создаём файл для коммита
        $filename = $dir.'/file-'.uniqid().'.txt';
        file_put_contents($filename, $message);

        $this->runGitCommand(['add', '.'], $dir);
        $this->runGitCommand(['commit', '-m', $message], $dir);
    }

    private function createTag(string $tag, bool $annotated = false, ?string $dir = null): void
    {
        $dir = $dir ?? $this->tempDir;

        if ($annotated) {
            $this->runGitCommand(['tag', '-a', $tag, '-m', "Annotated tag $tag"], $dir);
        } else {
            $this->runGitCommand(['tag', $tag], $dir);
        }
    }

    private function runGitCommand(array $command, ?string $dir = null): void
    {
        $dir = $dir ?? $this->tempDir;

        $process = new Process(array_merge(['git'], $command));
        $process->setWorkingDirectory($dir);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Git command failed: '.implode(' ', $command)."\n".$process->getErrorOutput()
            );
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir.'/'.$item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
