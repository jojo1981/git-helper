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

use Jojo1981\GitTag\GitHelperAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;

/**
 * @package Jojo1981\GitTag\Command
 */
class CleanCommand extends GitHelperAwareCommand
{
    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('clean');
        $this->setDescription('Remove old branches');
        $this->setHelp('This command remove old branches which are already merged');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws ProcessInvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getGitHelper()->fetch();
        $branches = $this->getGitHelper()->getMergedBranches();

        if (empty($branches)) {
            $output->writeln('<info>No branches to remove</info>');
        } else {
            foreach ($branches as $branch) {
                $output->writeln('Remove local branch: <info>' . $branch . '</info>');
                $this->getGitHelper()->removeLocalBranch($branch);
            }
            $output->writeln('Remote prune <info>origin</info>');
            $this->getGitHelper()->remotePruneOrigin();
        }

        return 0;
    }
}
