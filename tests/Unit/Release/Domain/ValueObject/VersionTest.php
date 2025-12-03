<?php

declare(strict_types=1);

namespace Tests\Unit\Release\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RunTracker\Release\Domain\ValueObject\ReleaseType;
use RunTracker\Release\Domain\ValueObject\Version;

class VersionTest extends TestCase
{
    #[Test] #[DataProvider('validVersionProvider')]
    public function it_parses_valid_version_strings(string $input, int $major, int $minor, int $patch): void
    {
        $version = Version::fromString($input);

        $this->assertSame($major, $version->major());
        $this->assertSame($minor, $version->minor());
        $this->assertSame($patch, $version->patch());
    }

    public static function validVersionProvider(): array
    {
        return [
            'simple version' => ['1.2.3', 1, 2, 3],
            'with lowercase v' => ['v1.2.3', 1, 2, 3],
            'with uppercase V' => ['V1.2.3', 1, 2, 3],
            'all zeros' => ['0.0.0', 0, 0, 0],
            'large numbers' => ['999.888.777', 999, 888, 777],
            'single digits' => ['1.0.0', 1, 0, 0],
        ];
    }

    #[Test] #[DataProvider('invalidVersionProvider')]
    public function it_throws_exception_for_invalid_version_formats(string $invalidVersion): void
    {
        $this->expectException(InvalidArgumentException::class);

        Version::fromString($invalidVersion);
    }

    public static function invalidVersionProvider(): array
    {
        return [
            'empty string' => [''],
            'single number' => ['1'],
            'two components' => ['1.0'],
            'four components' => ['1.0.0.0'],
            'non-numeric major' => ['abc.0.0'],
            'non-numeric minor' => ['1.abc.0'],
            'non-numeric patch' => ['1.0.abc'],
            'negative major' => ['-1.0.0'],
            'with spaces' => ['1. 2.3'],
            'only dots' => ['...'],
            'trailing dot' => ['1.0.0.'],
            'leading dot' => ['.1.0.0'],
            'with dash' => ['1.0.0-beta'],
            'with plus' => ['1.0.0+build'],
            'leading zeros' => ['01.02.03'],
        ];
    }

    #[Test]
    public function it_includes_helpful_error_message(): void
    {
        try {
            Version::fromString('invalid');
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid version format', $e->getMessage());
        }
    }

    #[DataProvider('equalVersionsProvider')] #[Test]
    public function it_returns_true_when_versions_are_equal(
        Version $v1,
        Version $v2
    ): void {
        $this->assertTrue($v1->equals($v2));
        $this->assertTrue($v2->equals($v1)); // Symmetry
    }

    public static function equalVersionsProvider(): array
    {
        return [
            'same simple version' => [
                Version::fromString('1.2.3'),
                Version::fromString('1.2.3'),
            ],
            'both zeros' => [
                Version::fromString('0.0.0'),
                Version::fromString('0.0.0'),
            ],
            'large numbers' => [
                Version::fromString('999.888.777'),
                Version::fromString('999.888.777'),
            ],
        ];
    }

    #[DataProvider('notEqualVersionsProvider')] #[Test]
    public function it_returns_false_when_versions_are_not_equal(
        Version $v1,
        Version $v2
    ): void {
        $this->assertFalse($v1->equals($v2));
        $this->assertFalse($v2->equals($v1)); // Symmetry
    }

    public static function notEqualVersionsProvider(): array
    {
        return [
            'different major' => [
                Version::fromString('1.0.0'),
                Version::fromString('2.0.0'),
            ],
            'different minor' => [
                Version::fromString('1.2.0'),
                Version::fromString('1.3.0'),
            ],
            'different patch' => [
                Version::fromString('1.2.3'),
                Version::fromString('1.2.4'),
            ],
            'completely different' => [
                Version::fromString('1.2.3'),
                Version::fromString('4.5.6'),
            ],
        ];
    }

    #[Test] #[DataProvider('greaterThanProvider')]
    public function it_returns_true_when_version_is_greater(
        Version $greater,
        Version $lesser
    ): void {
        $this->assertTrue($greater->isGreaterThan($lesser));
        $this->assertFalse($lesser->isGreaterThan($greater)); // Inverse
    }

    public static function greaterThanProvider(): array
    {
        return [
            'greater major' => [
                Version::fromString('2.0.0'),
                Version::fromString('1.9.9'),
            ],
            'greater minor with same major' => [
                Version::fromString('1.5.0'),
                Version::fromString('1.4.9'),
            ],
            'greater patch with same major and minor' => [
                Version::fromString('1.2.4'),
                Version::fromString('1.2.3'),
            ],
            'simple increment' => [
                Version::fromString('1.0.1'),
                Version::fromString('1.0.0'),
            ],
            'major trumps minor and patch' => [
                Version::fromString('2.0.0'),
                Version::fromString('1.99.99'),
            ],
            'minor trumps patch' => [
                Version::fromString('1.3.0'),
                Version::fromString('1.2.99'),
            ],
        ];
    }

    #[Test]
    public function it_returns_false_when_versions_are_equal_in_is_greater_than(): void
    {
        $v1 = Version::fromString('1.2.3');
        $v2 = Version::fromString('1.2.3');

        $this->assertFalse($v1->isGreaterThan($v2));
        $this->assertFalse($v2->isGreaterThan($v1));
    }

    #[DataProvider('incrementMajorProvider')] #[Test]
    public function it_increments_major_version_and_resets_minor_and_patch(
        Version $original,
        Version $expected
    ): void {
        $incremented = $original->increment(ReleaseType::MAJOR);

        $this->assertTrue($incremented->equals($expected));
        // Ensure immutability
        $this->assertFalse($original->equals($incremented));
    }

    public static function incrementMajorProvider(): array
    {
        return [
            'from 0.0.0' => [
                Version::fromString('0.0.0'),
                Version::fromString('1.0.0'),
            ],
            'from 1.2.3' => [
                Version::fromString('1.2.3'),
                Version::fromString('2.0.0'),
            ],
            'from 5.9.9' => [
                Version::fromString('5.9.9'),
                Version::fromString('6.0.0'),
            ],
        ];
    }

    #[DataProvider('incrementMinorProvider')] #[Test]
    public function it_increments_minor_version_and_resets_patch(
        Version $original,
        Version $expected
    ): void {
        $incremented = $original->increment(ReleaseType::MINOR);

        $this->assertTrue($incremented->equals($expected));
        // Ensure immutability
        $this->assertFalse($original->equals($incremented));
    }

    public static function incrementMinorProvider(): array
    {
        return [
            'from 0.0.0' => [
                Version::fromString('0.0.0'),
                Version::fromString('0.1.0'),
            ],
            'from 1.2.3' => [
                Version::fromString('1.2.3'),
                Version::fromString('1.3.0'),
            ],
            'from 2.9.5' => [
                Version::fromString('2.9.5'),
                Version::fromString('2.10.0'),
            ],
        ];
    }

    #[DataProvider('incrementPatchProvider')] #[Test]
    public function it_increments_patch_version_only(
        Version $original,
        Version $expected
    ): void {
        $incremented = $original->increment(ReleaseType::PATCH);

        $this->assertTrue($incremented->equals($expected));
        // Ensure immutability
        $this->assertFalse($original->equals($incremented));
    }

    public static function incrementPatchProvider(): array
    {
        return [
            'from 0.0.0' => [
                Version::fromString('0.0.0'),
                Version::fromString('0.0.1'),
            ],
            'from 1.2.3' => [
                Version::fromString('1.2.3'),
                Version::fromString('1.2.4'),
            ],
            'from 5.9.99' => [
                Version::fromString('5.9.99'),
                Version::fromString('5.9.100'),
            ],
        ];
    }

    #[Test]
    public function increment_returns_new_instance_ensuring_immutability(): void
    {
        $original = Version::fromString('v1.2.3');

        $majorIncremented = $original->increment(ReleaseType::MAJOR);
        $minorIncremented = $original->increment(ReleaseType::MINOR);
        $patchIncremented = $original->increment(ReleaseType::PATCH);

        // Original unchanged
        $this->assertSame(1, $original->major());
        $this->assertSame(2, $original->minor());
        $this->assertSame(3, $original->patch());

        // New instances created
        $this->assertNotSame($original, $majorIncremented);
        $this->assertNotSame($original, $minorIncremented);
        $this->assertNotSame($original, $patchIncremented);
    }

    #[Test]
    public function it_handles_transitivity_in_comparisons(): void
    {
        $v1 = Version::fromString('v1.0.0');
        $v2 = Version::fromString('V2.0.0');
        $v3 = Version::fromString('v3.0.0');

        $this->assertTrue($v1->isGreaterThan($v2) === false);
        $this->assertTrue($v2->isGreaterThan($v1) === true);
        $this->assertTrue($v2->isGreaterThan($v3) === false);
        $this->assertTrue($v3->isGreaterThan($v1) === true); // Transitivity
    }
}
