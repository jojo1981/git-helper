<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) 2020 Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
namespace Jojo1981\GitTag\Command;

use InvalidArgumentException;
use Jojo1981\GitTag\GitHelperAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Throwable;

/**
 * @package Jojo1981\GitTag\Command
 */
class ShowTagCommand extends GitHelperAwareCommand
{
    /** @var string */
    public const NAME = 'show-tag';

    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName(self::NAME);
        $this->setDescription('Show current tag');
        $this->setHelp('This command shows the current tag available');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getGitHelper()->fetch();
        try {
            $localVersion = $this->getGitHelper()->getLocalVersion();
        } catch (Throwable $exception) {
            $localVersion = null;
        }
        try {
            $remoteVersion = $this->getGitHelper()->getRemoteVersion();
        } catch (Throwable $exception) {
            $remoteVersion = null;
        }

        if (null === $localVersion) {
            $output->writeln('<error>Local version could not be determined</error>');
        } else {
            $output->writeln('Local version: <info>' . $localVersion->getAsString() . '</info>');
        }

        if (null === $remoteVersion) {
            $output->writeln('<error>Remote version could not be determined</error>');
        } else {
            $output->writeln('Remote version: <info>' . $remoteVersion->getAsString() . '</info>');
        }

        if (null !== $localVersion && null !== $remoteVersion && $localVersion->isNotEqual($remoteVersion)) {
            $output->writeln('<error>Local and remote versions are NOT equal</error>');
        }

        return 0;
    }
}
