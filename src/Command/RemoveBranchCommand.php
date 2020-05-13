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
use LogicException;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use function array_key_exists;
use function sprintf;

/**
 * @package Jojo1981\GitTag\Command
 */
class RemoveBranchCommand extends GitHelperAwareCommand
{
    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('remove-branch');
        $this->setDescription('Removes a git branch.');
        $this->setHelp('This command removes a branch');
        $this->addArgument(
            'branch',
            InputArgument::REQUIRED,
            'The branch name of the branch to remove'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleLogicException
     * @throws ConsoleRuntimeException
     * @throws LogicException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessFailedException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $input->getArgument('branch')) {
            $output->writeln('<error>Value for required argument `branch` not given</error>');
            $output->writeln('');
            $branches = $this->getGitHelper()->getBranches();
            if (empty($branches)) {
                $output->writeln('<error>There are no branches to remove.</error>');
                return;
            }
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the mode to use for tagging (defaults to <info>patch</info>):',
                $branches
            );
            $input->setArgument('branch', $helper->ask($input, $output, $question));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleRuntimeException
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (null !== ($branch = $input->getArgument('branch')) && $branch === $this->getGitHelper()->getLocalBranch()) {
            throw new ConsoleRuntimeException(sprintf(
                'Invalid value: `%s` for required argument `branch`, can not remove the current branch',
                $branch
            ));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleLogicException
     * @throws ConsoleRuntimeException
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $map = $this->getGitHelper()->getBranchMap();
        $branch = $input->getArgument('branch');

        $this->getGitHelper()->removeLocalBranch($branch);
        $output->writeln(sprintf('Local branch: <info>%s</info> is successfully removed.', $branch));

        if (array_key_exists($branch, $map) && $input->isInteractive()) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('Do you also want to remove the remote branch: `%s` [y/N]? ', $map[$branch]),
                false
            );

            if ($helper->ask($input, $output, $question)) {
                $this->getGitHelper()->removeRemoteBranch($map[$branch]);
                $output->writeln(sprintf('Remote branch: <info>%s</info> is successfully removed.', $map[$branch]));
            }
        }

        return 0;
    }
}
