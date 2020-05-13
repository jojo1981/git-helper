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
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Throwable;
use function sprintf;

/**
 * @package Jojo1981\GitTag\Command
 */
class CreateBranchCommand extends GitHelperAwareCommand
{
    use LockableTrait;

    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('create-branch');
        $this->setDescription('Creates a new git branch.');
        $this->setHelp('This command create a new git branch and set the remote upstream');
        $this->addArgument(
            'branch',
            InputArgument::REQUIRED,
            'The branch name to create'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ConsoleLogicException
     * @throws ConsoleRuntimeException
     * @throws ConsoleInvalidArgumentException
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $input->getArgument('branch')) {
            $output->writeln('<error>Value for required argument `branch` not given</error>');
            $output->writeln('');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new Question('Please enter the branch name for the branch to create: ');
            do {
                $answer = $helper->ask($input, $output, $question);
                if (null === $answer) {
                    $output->writeln('<error>No valid branch name entered.</error>');
                }
            } while (null === $answer);
            $input->setArgument('branch', $answer);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LockReleasingException
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleLogicException
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 1;
        }

        try {
            $branchName = $input->getArgument('branch');
            $this->getGitHelper()->pull();
            $this->getGitHelper()->createBranch($branchName);
            $output->writeln(sprintf('Branch <info>%s</info> is successfully created.', $branchName));
        } catch (Throwable $exception) {
            $this->release();
            throw $exception;
        }

        return 0;
    }
}
