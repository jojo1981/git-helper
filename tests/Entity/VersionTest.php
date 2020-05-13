<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) 2020 Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
namespace Jojo1981\GitTag\TestSuite\Entity;

use InvalidArgumentException;
use Jojo1981\GitTag\Entity\Version;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException as SebastianBergmannInvalidArgumentException;

/**
 * @package Jojo1981\GitTag\TestSuite\Entity
 */
class VersionTest extends TestCase
{
    /**
     * @test
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws SebastianBergmannInvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function getAsStringShouldReturnTheCorrectValue(): void
    {
        $this->assertEquals('1.2.45', (new Version(1, 2, 45))->getAsString());
        $this->assertEquals('1.2.45', Version::createFromString('1.2.45')->getAsString());
    }

    /**
     * @test
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws SebastianBergmannInvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function getNextMajorShouldReturnTheCorrectVersion(): void
    {
        $this->assertEquals(new Version(13, 0, 0), Version::createFromString('12.3.56')->getNextMajor());
    }

    /**
     * @test
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws SebastianBergmannInvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function getNextMinorShouldReturnTheCorrectVersion(): void
    {
        $this->assertEquals(new Version(12, 4, 0), Version::createFromString('12.3.56')->getNextMinor());
    }

    /**
     * @test
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws SebastianBergmannInvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function getNextPatchShouldReturnTheCorrectVersion(): void
    {
        $this->assertEquals(new Version(12, 3, 57), Version::createFromString('12.3.56')->getNextPatch());
    }
}
