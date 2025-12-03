<?php

declare(strict_types=1);

namespace RunTracker\Release\Domain\ValueObject;

final readonly class Version
{
    private function __construct(
        private int $major,
        private int $minor,
        private int $patch,
    ) {}

    public static function initial(): self
    {
        return new self(1, 0, 0);
    }

    public static function fromString(string $version): self
    {
        $version = ltrim($version, 'vV');
        if (! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version, $matches)) {
            throw new \InvalidArgumentException(
                "Invalid version format: '$version'. Expected format: 'X.Y.Z'"
            );
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    public function toString(): string
    {
        return "v{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function major(): int
    {
        return $this->major;
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function patch(): int
    {
        return $this->patch;
    }

    public function equals(Version $other): bool
    {
        return version_compare($this->toString(), $other->toString(), '=');
    }

    public function isGreaterThan(Version $other): bool
    {
        return version_compare($this->toString(), $other->toString(), '>');
    }

    public function increment(ReleaseType $type): self
    {
        return match ($type) {
            ReleaseType::MAJOR => new self($this->major + 1, 0, 0),
            ReleaseType::MINOR => new self($this->major, $this->minor + 1, 0),
            ReleaseType::PATCH => new self($this->major, $this->minor, $this->patch + 1),
        };
    }
}
