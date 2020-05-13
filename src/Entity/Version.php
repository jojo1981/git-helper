<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) 2020 Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
namespace Jojo1981\GitTag\Entity;

use InvalidArgumentException;
use function count;
use function explode;
use function is_numeric;
use function sprintf;

/**
 * @package Jojo1981\GitTag\Entity
 */
final class Version
{
    /** @var string */
    private const VERSION_FORMAT = '%d.%d.%d';

    /** @var int */
    private $major;

    /** @var int */
    private $minor;

    /** @var int */
    private $patch;

    /**
     * @param int $major
     * @param int $minor
     * @param int $patch
     */
    public function __construct(int $major, int $minor, int $patch)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
    }

    /**
     * @param string $versionString
     * @return Version
     * @throws InvalidArgumentException
     */
    public static function createFromString(string $versionString): Version
    {
        $parts = explode('.', $versionString);
        $exception = new InvalidArgumentException(sprintf(
            'Invalid version string: `%s` given, expect format: xx.xx.xx',
            $versionString
        ));
        if (3 !== count($parts)) {
            throw $exception;
        }
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                throw $exception;
            }
        }

        return new self((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    }

    /**
     * @return int
     */
    public function getMajor(): int
    {
        return $this->major;
    }

    /**
     * @return int
     */
    public function getMinor(): int
    {
        return $this->minor;
    }

    /**
     * @return int
     */
    public function getPatch(): int
    {
        return $this->patch;
    }

    /**
     * @return string
     */
    public function getAsString(): string
    {
        return sprintf(self::VERSION_FORMAT, $this->major, $this->minor, $this->patch);
    }

    /**
     * @return Version
     * @throws InvalidArgumentException
     */
    public function getNextMajor(): Version
    {
        return self::createFromString(sprintf(self::VERSION_FORMAT, $this->major + 1, 0, 0));
    }

    /**
     * @return Version
     * @throws InvalidArgumentException
     */
    public function getNextMinor(): Version
    {
        return self::createFromString(sprintf(self::VERSION_FORMAT, $this->major, $this->minor + 1, 0));
    }

    /**
     * @return Version
     * @throws InvalidArgumentException
     */
    public function getNextPatch(): Version
    {
        return self::createFromString(sprintf(self::VERSION_FORMAT, $this->major, $this->minor, $this->patch + 1));
    }

    /**
     * @param Version $other
     * @return int
     */
    public function compare(Version $other): int
    {
        $thisNormalized = $this->patch + ($this->minor * 10) + ($this->major * 100);
        $otherNormalized = $other->patch + ($other->minor * 10) + ($other->major * 100);

        if ($thisNormalized === $otherNormalized) {
            return 0;
        }

        return $thisNormalized > $otherNormalized ? 1 : -1;
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function isEqual(Version $other): bool
    {
        return 0 === $this->compare($other);
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function isNotEqual(Version $other): bool
    {
        return !$this->isEqual($other);
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function lessThan(Version $other): bool
    {
        return -1 === $this->compare($other);
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function lessThanOrEqual(Version $other): bool
    {
        return -1 === $this->compare($other) || 0 === $this->compare($other);
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function greaterThan(Version $other): bool
    {
        return 1 === $this->compare($other);
    }

    /**
     * @param Version $other
     * @return bool
     */
    public function greaterThanOrEqual(Version $other): bool
    {
        return 1 === $this->compare($other) || 0 === $this->compare($other);
    }
}
