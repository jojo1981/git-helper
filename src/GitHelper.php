<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) 2020 Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
namespace Jojo1981\GitTag;

use InvalidArgumentException;
use Jojo1981\GitTag\Entity\Version;
use RuntimeException;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use function array_filter;
use function array_map;
use function count;
use function explode;
use function sprintf;
use function stripos;
use function trim;

/**
 * @package Jojo1981\GitTag
 */
final class GitHelper
{
    /**
     * @return string
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function getOriginalRemoteRepository(): string
    {
        return $this->runProcess(Process::fromShellCommandline('git config --get remote.origin.url'));
    }

    /**
     * @return Version
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws InvalidArgumentException
     */
    public function getLocalVersion(): Version
    {
        return Version::createFromString($this->runProcess(Process::fromShellCommandline(
            'git describe --abbrev=0 --tags'
        )));
    }

    /**
     * @return Version
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getRemoteVersion(): Version
    {
        $versionString = $this->runProcess(Process::fromShellCommandline(sprintf(
            'git ls-remote --tags --refs --sort="v:refname" %s | tail -n1 | sed \'s/.*\///\'',
            $this->getOriginalRemoteRepository()
        )));

        if ('' === $versionString) {
            throw new RuntimeException('Could not find remote version');
        }

        return Version::createFromString($versionString);
    }

    /**
     * @param Version $tag
     * @return bool
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function localTagExists(Version $tag): bool
    {
        try {
            $result = $this->runProcess(Process::fromShellCommandline('git rev-parse ' . $tag->getAsString()));
        } catch (ProcessFailedException $exception) {
            return false;
        }

        return !empty($result);
    }

    /**
     * @param Version $tag
     * @return bool
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function remoteTagExists(Version $tag): bool
    {
        try {
            $tags = $this->runProcessAndGetLines(Process::fromShellCommandline(sprintf(
                'git ls-remote --tags --refs --sort="v:refname" %s | sed \'s/.*\///\' | grep ' . $tag->getAsString(),
                $this->getOriginalRemoteRepository()
            )));
        } catch (ProcessFailedException $exception) {
            return false;
        }

        return 1 === count($tags);
    }

    /**
     * @param Version $tag
     * @return void
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function removeLocalTag(Version $tag): void
    {
        $this->runProcess(Process::fromShellCommandline('git tag -d ' . $tag->getAsString()));
    }

    /**
     * @param Version $tag
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function removeRemoteTag(Version $tag): void
    {
        $this->runProcess(Process::fromShellCommandline('git push --delete origin ' . $tag->getAsString()));
    }

    /**
     * @return string[]
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function getLocalTags(): array
    {
        return $this->runProcessAndGetLines(Process::fromShellCommandline('git tag'));
    }

    /**
     * @return array
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function getRemoteTags(): array
    {
        $tags = $this->runProcessAndGetLines(Process::fromShellCommandline(sprintf(
            'git ls-remote --tags --refs --sort="v:refname" %s | sed \'s/.*\///\'',
            $this->getOriginalRemoteRepository()
        )));

        return array_map(Version::class . '::createFromString', $tags);
    }

    /**
     * @param Version $tag
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function createLocalTag(Version $tag): void
    {
        $this->runProcess(Process::fromShellCommandline(sprintf(
            'git tag -a %s -m "%s"',
            $tag->getAsString(),
            $tag->getAsString()
        )));
    }

    /**
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function push(): void
    {
        $this->runProcess(Process::fromShellCommandline('git push'));
    }

    /**
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function pushTags(): void
    {
        $this->runProcess(Process::fromShellCommandline('git push --tags'));
    }

    /**
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function rollbackLastCommit(): void
    {
        $this->runProcess(Process::fromShellCommandline('git reset --soft HEAD~'));
    }

    /**
     * @return bool
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function isTagged(): bool
    {
        try {
            $headCommitHash = $this->runProcess(Process::fromShellCommandline('git rev-parse HEAD'));
            $result = $this->runProcess(Process::fromShellCommandline('git describe --contains ' . $headCommitHash));
        } catch (ProcessFailedException $exception) {
            return false;
        }

        return !empty($result);
    }

    /**
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function fetch(): void
    {
        $this->runProcess(Process::fromShellCommandline('git fetch'));
    }

    /**
     * @param string $branch
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function removeLocalBranch(string $branch): void
    {
        $this->runProcess(Process::fromShellCommandline('git branch -d ' . $branch));
    }

    /**
     * @param array $excluded
     * @return string[]
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    public function getMergedBranches(array $excluded = ['master', 'dev', 'release']): array
    {
        try {
            $branches = $this->runProcessAndGetLines(Process::fromShellCommandline(
                'git branch --merged | grep -v "\*"'
            ));
        } catch (ProcessFailedException $exception) {
            $branches = [];
        }
        return array_filter(
            $branches,
            function (string $branch) use ($excluded): bool {
                return !$this->contains($branch, $excluded);
            }
        );
    }

    /**
     * @return void
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    public function remotePruneOrigin(): void
    {
        $this->runProcess(Process::fromShellCommandline('git remote prune origin'));
    }

    /**
     * @param Process $process
     * @return string[]
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessFailedException
     */
    private function runProcessAndGetLines(Process $process): array
    {
        return explode(PHP_EOL, $this->runProcess($process));
    }

    /**
     * @param Process $process
     * @return string
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    private function runProcess(Process $process): string
    {
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }

    /**
     * @param string[] $excluded
     * @param string $branch
     * @return bool
     */
    private function contains(string $branch, array $excluded): bool
    {
        foreach ($excluded as $item) {
            if ($this->isLike($branch, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function isLike(string $haystack, string $needle): bool
    {
        return false !== stripos($haystack, $needle);
    }
}