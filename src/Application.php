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
use Jojo1981\GitTag\Command\SelfUpdateCommand;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function basename;
use function sprintf;

/**
 * @author Joost Nijhuis <jnijhuis81@gmail.com>
 * @package Jojo1981\GitTag
 */
final class Application extends BaseApplication
{
    /** @var string */
    public const NAME = 'git-helper';

    /** @var string */
    public const VERSION = '@package_version@';

    /** @var string */
    public const BRANCH_ALIAS_VERSION = '@package_branch_alias_version@';

    /** @var string */
    public const RELEASE_DATE = '@release_date@';

    /** @var Updater */
    private $updater;

    /**
     * @param Updater $updater
     */
    public function __construct(Updater $updater)
    {
        parent::__construct(self::NAME, '@package_version' . '@' === self::VERSION ? 'development' : self::VERSION);
        $this->updater = $updater;
    }

    /**
     * @return string
     */
    public function getLongVersion(): string
    {
        if ('@package_branch_alias_version' . '@' !== self::BRANCH_ALIAS_VERSION) {
            return sprintf(
                '<info>%s</info> version <comment>%s (%s)</comment> %s',
                $this->getName(),
                self::BRANCH_ALIAS_VERSION,
                $this->getVersion(),
                self::RELEASE_DATE
            );
        }

        return parent::getLongVersion();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws NamespaceNotFoundException
     * @throws Throwable
     * @throws CommandNotFoundException
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $commandName = null;
        if ($name = $this->getCommandName($input)) {
            try {
                $commandName = $this->find($name)->getName();
            } catch (Throwable $exception) {
                // nothing to do
            }
        }

        if ('@package_branch_alias_version' . '@' !== self::BRANCH_ALIAS_VERSION
            && $commandName !== SelfUpdateCommand::NAME && $this->updater->hasUpdate()
        ) {
            $output->writeln(sprintf(
                '<info>Warning: There is a new update available: %s. It is recommended to update it by running "%s '
                . 'self-update" to get the latest version.</info>',
                $this->updater->getNewVersion(),
                basename($_SERVER['PHP_SELF'])
            ));
        }

        return parent::doRun($input, $output);
    }
}
