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

use Humbug\SelfUpdate\Updater;
use Jojo1981\GitTag\Command\CleanCommand;
use Jojo1981\GitTag\Command\CreateTagCommand;
use Jojo1981\GitTag\Command\RemoveTagCommand;
use Jojo1981\GitTag\Command\RollbackLastCommitCommand;
use Jojo1981\GitTag\Command\SelfUpdateCommand;
use Jojo1981\GitTag\Command\ShowTagCommand;
use Jojo1981\GitTag\SelfUpdate\Strategy\GithubStrategy;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @package Jojo1981\GitTag
 */
final class ApplicationFactory
{
    /** @var Application */
    private $application;

    /**
     * @return Application
     * @throws LogicException
     */
    public function createApplication(): Application
    {
        if (null === $this->application) {
            $gitHelper = new GitHelper();
            $updater = new Updater(null, false);

            $this->application = new Application($updater);
            $this->application->add(new CleanCommand($gitHelper));
            $this->application->add(new CreateTagCommand($gitHelper));
            $this->application->add(new RemoveTagCommand($gitHelper));
            $this->application->add(new ShowTagCommand($gitHelper));
            $this->application->add(new RollbackLastCommitCommand($gitHelper));
            if (!$this->isDevelopment()) {
                $this->application->add(new SelfUpdateCommand($updater, new Filesystem()));
            }

            $updater->setStrategyObject(new GithubStrategy(
                'jojo1981',
                'git-helper',
                $this->application->getVersion()
            ));
        }

        return $this->application;
    }

    /**
     * @return bool
     */
    private function isDevelopment(): bool
    {
        return '@package_version' . '@' === Application::VERSION;
    }
}
