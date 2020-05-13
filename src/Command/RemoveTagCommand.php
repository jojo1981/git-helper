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
use Jojo1981\GitTag\Entity\Version;
use Jojo1981\GitTag\GitHelperAwareCommand;
use LogicException;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Process\Exception\InvalidArgumentException as ProcessInvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use function sprintf;
use function trim;

/**
 * @package Jojo1981\GitTag\Command
 */
class RemoveTagCommand extends GitHelperAwareCommand
{
    use LockableTrait;

    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('remove-tag');
        $this->setDescription('Remove tag');
        $this->setHelp('This command remove a tag locally and from the remote.');
        $this->addArgument('tag', InputArgument::REQUIRED, 'git tag to remove');
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
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessFailedException
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $tag = trim($input->getArgument('tag') ?? '');
        if (empty($tag)) {
            $output->writeln('<error>Value for required argument `tag` not given</error>');
            $output->writeln('');
            $tags = $this->getGitHelper()->getLocalTags();

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the tag which needs to be removed:',
                $tags
            );
            $input->setArgument('tag', $helper->ask($input, $output, $question));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleLogicException
     * @throws InvalidArgumentException
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws LockReleasingException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 1;
        }

        $tag = Version::createFromString(trim($input->getArgument('tag')));

        $this->getGitHelper()->fetch();
        $locallyExists = $this->getGitHelper()->localTagExists($tag);
        $remoteExists = $this->getGitHelper()->remoteTagExists($tag);
        if (!$locallyExists && !$remoteExists) {
            $output->writeln(sprintf(
                '<error>Tag: %s doesn\'t exists locally and also not remotely</error>',
                $tag->getAsString()
            ));
            $this->release();

            return 1;
        }

        if ($locallyExists) {
            $output->writeln('Local tag: ' . $tag->getAsString() . ' exists');
            $this->getGitHelper()->removeLocalTag($tag);
            $output->writeln('Local tag successfully removed');
        }

        if ($remoteExists) {
            $output->writeln('Remote tag: ' . $tag->getAsString() . ' exists');
            $this->getGitHelper()->removeRemoteTag($tag);
            $output->writeln('Remote tag successfully removed');
        }

        $this->release();

        return 0;
    }
}
