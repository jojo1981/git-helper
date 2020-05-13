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
use RuntimeException;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use function sprintf;

/**
 * @package Jojo1981\GitTag\Command
 */
class HardResetCommand extends GitHelperAwareCommand
{
    use LockableTrait;

    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('hard-reset');
        $this->setDescription('Reset the local branch');
        $this->setHelp('The command reset the local branch and make it equal to the remote branch');
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force the command to perform without a warning (required when interactive mode is disabled)'
        );
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
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 1;
        }

        $force = $input->getOption('force');
        if (!$force && !$input->isInteractive()) {
            $output->writeln(
                '<error>Interaction is disabled and force is NOT enabled so this command will quit</error>'
            );

            return 2;
        }

        $localBranch = $this->getGitHelper()->getLocalBranch();
        $upstreamRemoteBranch = $this->getGitHelper()->getUpstreamRemoteBranch();
        if (null === $upstreamRemoteBranch) {
            $output->writeln(sprintf(
                '<error>No upstream branch available for the local branch: %s</error>',
                $localBranch
            ));

            return 3;
        }

        $this->getGitHelper()->fetch();
        $isAhead = $this->getGitHelper()->isAhead();
        $isBehind = $this->getGitHelper()->isBehind();
        if (!$isAhead && !$isBehind) {
            $output->writeln('<info>Local branch and remote branch are not diverted</info>');

            return 0;
        }

        if (!($isAhead && $isBehind)) {
            if ($isAhead) {
                $output->writeln('<info>Local branch is only ahead so only a push is needed</info>');
            } else {
                $output->writeln('<info>Local branch is only behind so only a pull is needed</info>');
            }
        }

        if (!$force) {
            $output->writeln('<comment>Local changes will be stashed, but local commits are lost.</comment>');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Are you sure you want to hard reset the local branch: `%s` from the remote branch: `%s` [y/N]? ',
                    $localBranch,
                    $upstreamRemoteBranch
                ),
                false
            );
            if (false === $helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');

                return 0;
            }
        }

        if ($this->getGitHelper()->hasLocalChanges()) {
            $output->writeln('There are local changes, so push them to the stash stack.');
            $this->getGitHelper()->pushStash();
        }

        $this->getGitHelper()->hardResetBranch($upstreamRemoteBranch);

        return 0;
    }
}
