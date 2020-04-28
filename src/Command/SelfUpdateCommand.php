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

use Humbug\SelfUpdate\Updater;
use Jojo1981\GitTag\SelfUpdate\Strategy\GithubStrategy;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @package Jojo1981\GitTag\Command
 */
class SelfUpdateCommand extends Command
{
    /**
     * @return void
     * @throws ConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('self-update');
        $this->setDescription('Perform a self update to the newest stable version available');
        $this->setHelp('This command performs a self update to the newest stable version available');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->getApplication()) {
            throw new RuntimeException('Command runs without an application?');
        }

        $updater = new Updater(null, false);
        $updater->setStrategyObject(new GithubStrategy('jojo1981', 'git-helper', $this->getApplication()->getVersion()));
        try {
            $result = $updater->update();
            $output->writeln('<info>' . ($result ? 'Updated!' : 'No update needed!') . '</info>');
        } catch (Throwable $exception) {
            $output->writeln(
                '<error>Well, something happened! Either an oopsie or something involving hackers.</error>'
            );

            return 1;
        }

        return 0;
    }
}
