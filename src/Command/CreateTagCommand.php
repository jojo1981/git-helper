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
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
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
use function array_combine;
use function implode;
use function in_array;
use function sprintf;

/**
 * @package Jojo1981\GitTag\Command
 */
class CreateTagCommand extends GitHelperAwareCommand
{
    use LockableTrait;

    /** @var string */
    private const MODE_PATCH = 'patch';

    /** @var string */
    private const MODE_MINOR = 'minor';

    /** @var string */
    private const MODE_MAJOR = 'major';

    /** @var string[] */
    private const VALID_MODES = [
        self::MODE_PATCH,
        self::MODE_MINOR,
        self::MODE_MAJOR,
    ];

    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('create-tag');
        $this->setDescription('Creates a new git tag.');
        $this->setHelp('This command allows you to create a new git tag...');
        $this->addArgument(
            'mode',
            InputArgument::REQUIRED,
            sprintf('The mode to use for tagging, should be one of: [%s]', implode(', ', self::VALID_MODES))
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ConsoleRuntimeException
     * @throws ConsoleInvalidArgumentException
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (null !== ($mode = $input->getArgument('mode')) && !in_array($mode, self::VALID_MODES, true)) {
            throw new ConsoleRuntimeException(sprintf(
                'Invalid value: `%s` for required argument `mode`, value should be one of: [%s]',
                $mode,
                implode(', ', self::VALID_MODES)
            ));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ConsoleLogicException
     * @throws ConsoleRuntimeException
     * @throws LogicException
     * @throws ConsoleInvalidArgumentException
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $input->getArgument('mode')) {
            $output->writeln('<error>Value for required argument `mode` not given</error>');
            $output->writeln('');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the mode to use for tagging (defaults to <info>patch</info>):',
                array_combine(['1', '2', '3'], self::VALID_MODES),
                '1'
            );
            $input->setArgument('mode', $helper->ask($input, $output, $question));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ConsoleInvalidArgumentException
     * @throws ConsoleLogicException
     * @throws InvalidArgumentException
     * @throws LockReleasingException
     * @throws ProcessFailedException
     * @throws ProcessInvalidArgumentException
     * @throws ProcessLogicException
     * @throws ProcessRuntimeException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ConsoleRuntimeException
     * @throws RuntimeException
     * @throws CommandNotFoundException
     * @throws LockAcquiringException
     * @throws LockConflictedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 1;
        }

        $this->getGitHelper()->fetch();
        $localVersion = $this->getGitHelper()->getLocalVersion();
        $remoteVersion = $this->getGitHelper()->getRemoteVersion();
        if ($localVersion->isNotEqual($remoteVersion)) {
            $this->throwRuntimeException(sprintf(
                'Local version: %s and remote version: %s aren not equal',
                $localVersion->getAsString(),
                $remoteVersion->getAsString()
            ));
        }

        if ($this->getGitHelper()->isTagged()) {
            $this->throwRuntimeException('Already tagged');
        }

        if (null !== $application = $this->getApplication()) {
            $application->find(ShowTagCommand::NAME)->run(new ArrayInput([]), $output);
        }

        $mode = $input->getArgument('mode');
        $newVersion = null;
        switch ($mode) {
            case self::MODE_PATCH:
                $newVersion = $localVersion->getNextPatch();
                break;
            case self::MODE_MINOR:
                $newVersion = $localVersion->getNextMinor();
                break;
            case self::MODE_MAJOR:
                $newVersion = $localVersion->getNextMajor();
                break;
            default:
                $this->throwRuntimeException(sprintf(
                    'Invalid value: `%s` for required argument `mode`, value should be one of: [%s]',
                    $mode,
                    implode(', ', self::VALID_MODES)
                ));
        }

        $output->writeln(sprintf(
            'Updating <info>%s</info> to <info>%s</info>',
            $localVersion->getAsString(),
            $newVersion->getAsString()
        ));

        $this->getGitHelper()->createLocalTag($newVersion);
        $output->writeln(sprintf('Tag: <info>%s</info> created', $newVersion->getAsString()));
        $this->getGitHelper()->push();
        $this->getGitHelper()->pushTags();

        $this->release();

        return 0;
    }

    /**
     * @param string $message
     * @return void
     * @throws LockReleasingException
     * @throws ConsoleRuntimeException
     */
    private function throwRuntimeException(string $message): void
    {
        $this->release();

        throw new ConsoleRuntimeException($message);
    }
}
