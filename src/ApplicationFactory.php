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

use Jojo1981\GitTag\Command\CleanCommand;
use Jojo1981\GitTag\Command\CreateTagCommand;
use Jojo1981\GitTag\Command\RemoveTagCommand;
use Jojo1981\GitTag\Command\RollbackLastCommitCommand;
use Jojo1981\GitTag\Command\SelfUpdateCommand;
use Jojo1981\GitTag\Command\ShowTagCommand;
use Symfony\Component\Console\Exception\LogicException;

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
            $this->application = new Application();
            $this->application->add(new CleanCommand($gitHelper));
            $this->application->add(new CreateTagCommand($gitHelper));
            $this->application->add(new RemoveTagCommand($gitHelper));
            $this->application->add(new ShowTagCommand($gitHelper));
            $this->application->add(new RollbackLastCommitCommand($gitHelper));
            if ('@' . 'package_version' . '@' !== Application::VERSION) {
                $this->application->add(new SelfUpdateCommand());
            }
        }

        return $this->application;
    }
}
