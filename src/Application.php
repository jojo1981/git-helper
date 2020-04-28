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

use Symfony\Component\Console\Application as BaseApplication;

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

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(self::NAME, '@package_version' . '@' === self::VERSION ? 'development' : self::VERSION);
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
}
