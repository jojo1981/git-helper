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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException as ConsoleLogicException;

/**
 * @package Jojo1981\GitTag\Command
 */
abstract class GitHelperAwareCommand extends Command
{
    /** @var GitHelper */
    private $gitHelper;

    /**
     * @param GitHelper $gitHelper
     * @param string|null $name
     * @throws ConsoleLogicException
     */
    public function __construct(GitHelper $gitHelper, string $name = null)
    {
        parent::__construct($name);
        $this->gitHelper = $gitHelper;
    }

    /**
     * @return GitHelper
     */
    final protected function getGitHelper(): GitHelper
    {
        return $this->gitHelper;
    }
}
