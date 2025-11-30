<?php

declare(strict_types=1);

namespace Tests\Unit\Release\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RunTracker\Release\Application\Service\VersionCalculator;
use RunTracker\Release\Domain\Port\GitRepository;
use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Release\Domain\ValueObject\Version;

class VersionCalculatorTest extends TestCase
{
    private GitRepository $repository;

    private VersionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(GitRepository::class);
        $this->calculator = new VersionCalculator($this->repository);
    }

    /** @test */
    public function it_returns_explicit_version_when_provided(): void
    {
        // Repository не должен вызываться, если версия указана явно
        $this->repository->expects($this->never())
            ->method('getLastTag');

        $version = $this->calculator->calculateNextVersion('2.5.3', ReleaseType::MAJOR);

        $this->assertTrue($version->equals(Version::fromString('2.5.3')));
    }

    #[DataProvider('explicitVersionProvider')] #[Test]
    public function it_validates_explicit_version_format(
        string $explicitVersion
    ): void {
        $this->repository->expects($this->never())
            ->method('getLastTag');

        $version = $this->calculator->calculateNextVersion($explicitVersion, ReleaseType::PATCH);

        $this->assertTrue($version->equals(Version::fromString($explicitVersion)));
    }

    public static function explicitVersionProvider(): array
    {
        return [
            'simple version' => ['1.0.0'],
            'with v prefix' => ['v2.3.4'],
            'with V prefix' => ['V3.5.7'],
            'all zeros' => ['0.0.0'],
            'large numbers' => ['999.888.777'],
        ];
    }

    #[Test]
    public function it_throws_exception_for_invalid_explicit_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculateNextVersion('invalid', ReleaseType::MAJOR);
    }

    #[Test]
    public function it_returns_initial_version_when_no_tags_exist(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn(null);

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::PATCH);

        $this->assertTrue($version->equals(Version::initial()));
    }

    #[Test]
    public function it_increments_major_version_from_last_tag(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('1.5.3');

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::MAJOR);

        $this->assertTrue($version->equals(Version::fromString('2.0.0')));
    }

    #[Test]
    public function it_increments_minor_version_from_last_tag(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('1.5.3');

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::MINOR);

        $this->assertTrue($version->equals(Version::fromString('1.6.0')));
    }

    #[Test]
    public function it_increments_patch_version_from_last_tag(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('1.5.3');

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::PATCH);

        $this->assertTrue($version->equals(Version::fromString('1.5.4')));
    }

    #[DataProvider('versionIncrementProvider')] #[Test]
    public function it_correctly_increments_versions_based_on_type(
        string $lastTag,
        ReleaseType $type,
        string $expectedVersion
    ): void {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn($lastTag);

        $version = $this->calculator->calculateNextVersion(null, $type);

        $this->assertTrue($version->equals(Version::fromString($expectedVersion)));
    }

    public static function versionIncrementProvider(): array
    {
        return [
            'major from 0.0.0' => ['0.0.0', ReleaseType::MAJOR, '1.0.0'],
            'minor from 0.0.0' => ['0.0.0', ReleaseType::MINOR, '0.1.0'],
            'patch from 0.0.0' => ['0.0.0', ReleaseType::PATCH, '0.0.1'],

            'major from 2.9.9' => ['2.9.9', ReleaseType::MAJOR, '3.0.0'],
            'minor from 2.9.9' => ['2.9.9', ReleaseType::MINOR, '2.10.0'],
            'patch from 2.9.9' => ['2.9.9', ReleaseType::PATCH, '2.9.10'],

            'major with v prefix' => ['v5.3.2', ReleaseType::MAJOR, '6.0.0'],
            'minor with V prefix' => ['V5.3.2', ReleaseType::MINOR, '5.4.0'],
        ];
    }

    #[Test]
    public function it_handles_tag_with_v_prefix(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('v1.2.3');

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::PATCH);

        $this->assertTrue($version->equals(Version::fromString('1.2.4')));
    }

    #[Test]
    public function it_handles_tag_with_uppercase_v_prefix(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('V1.2.3');

        $version = $this->calculator->calculateNextVersion(null, ReleaseType::MINOR);

        $this->assertTrue($version->equals(Version::fromString('1.3.0')));
    }

    #[Test]
    public function it_prioritizes_explicit_version_over_repository_tags(): void
    {
        // Репозиторий не должен вызываться
        $this->repository->expects($this->never())
            ->method('getLastTag');

        $version = $this->calculator->calculateNextVersion('5.0.0', ReleaseType::MAJOR);

        $this->assertTrue($version->equals(Version::fromString('5.0.0')));
    }

    #[Test]
    public function it_throws_exception_when_repository_returns_invalid_tag(): void
    {
        $this->repository->expects($this->once())
            ->method('getLastTag')
            ->willReturn('invalid-tag');

        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculateNextVersion(null, ReleaseType::PATCH);
    }

    #[Test]
    public function it_handles_multiple_calculations_independently(): void
    {
        $this->repository->expects($this->exactly(2))
            ->method('getLastTag')
            ->willReturnOnConsecutiveCalls('1.0.0', '2.0.0');

        $version1 = $this->calculator->calculateNextVersion(null, ReleaseType::MAJOR);
        $version2 = $this->calculator->calculateNextVersion(null, ReleaseType::MINOR);

        $this->assertTrue($version1->equals(Version::fromString('2.0.0')));
        $this->assertTrue($version2->equals(Version::fromString('2.1.0')));
    }

    #[Test]
    public function it_uses_initial_version_when_repository_has_no_tags_for_each_release_type(): void
    {
        $this->repository->method('getLastTag')
            ->willReturn(null);

        $majorVersion = $this->calculator->calculateNextVersion(null, ReleaseType::MAJOR);
        $minorVersion = $this->calculator->calculateNextVersion(null, ReleaseType::MINOR);
        $patchVersion = $this->calculator->calculateNextVersion(null, ReleaseType::PATCH);

        // Все должны возвращать начальную версию
        $initial = Version::initial();
        $this->assertTrue($majorVersion->equals($initial));
        $this->assertTrue($minorVersion->equals($initial));
        $this->assertTrue($patchVersion->equals($initial));
    }
}
