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

use BadMethodCallException;
use Composer\Json\JsonFile;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use LogicException;
use Phar;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Seld\PharUtils\Linter;
use UnexpectedValueException;
use function base64_encode;
use function basename;
use function date;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function implode;
use function in_array;
use function is_string;
use function json_encode;
use function openssl_free_key;
use function openssl_pkey_get_private;
use function openssl_sign;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_repeat;
use function str_replace;
use function strcmp;
use function strlen;
use function strpos;
use function substr_count;
use function substr_replace;
use function token_get_all;
use function trim;
use function unlink;

/**
 * @package Jojo1981\GitTag
 */
final class Compiler
{
    /** @var string */
    private $projectDirectory;

    /** @var Filesystem */
    private $filesystem;

    /** @var string */
    private $buildDirectory;

    /** @var string */
    private $cacheDirectory;

    /** @var string */
    private $resourcesDirectory;

    /** @var string */
    private $vendorDirectory;

    /** @var string */
    private $sourceDirectory;

    /** @var string */
    private $version;

    /** @var DateTimeInterface */
    private $versionDate;

    /** @var string */
    private $branchAliasVersion;

    /**
     * @param string $projectDirectory
     * @param Filesystem $filesystem
     */
    public function __construct(string $projectDirectory, Filesystem $filesystem)
    {
        $this->projectDirectory = rtrim($projectDirectory, DIRECTORY_SEPARATOR);
        $this->buildDirectory = $this->projectDirectory . DIRECTORY_SEPARATOR . 'build';
        $this->cacheDirectory = $this->projectDirectory . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, ['var', 'cache', 'vendor']);
        $this->resourcesDirectory = $this->projectDirectory . DIRECTORY_SEPARATOR . 'resources';
        $this->vendorDirectory = $this->projectDirectory . DIRECTORY_SEPARATOR . 'vendor';
        $this->sourceDirectory = $this->projectDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem = $filesystem;
    }

    /**
     * @param string $pharFile
     * @return void
     * @throws Exception
     * @throws ProcessLogicException
     * @throws RuntimeException
     * @throws BadMethodCallException
     * @throws LogicException
     * @throws DirectoryNotFoundException
     * @throws ProcessSignaledException
     * @throws ProcessTimedOutException
     * @throws ProcessRuntimeException
     * @throws UnexpectedValueException
     */
    public function compile(string $pharFile): void
    {
        $pharFile = $this->buildDirectory . DIRECTORY_SEPARATOR . $pharFile;
        if (file_exists($pharFile)) {
            $this->writeLn('Phar file: ' . $pharFile . ' exists');
            unlink($pharFile);
            $this->writeLn('Phar file removed');
        } else {
            $this->writeLn('Phar file: ' . $pharFile . ' doesn\'t exists');
        }

        $process = Process::fromShellCommandline('git log --pretty="%H" -n1 HEAD', __DIR__);
        if (0 !== $process->run()) {
            throw new RuntimeException(
                'Can\'t run git log. You must ensure to run compile from composer git repository clone and that git '
                . 'binary is available.'
            );
        }
        $this->version = trim($process->getOutput());
        $this->writeLn('Version detected: '. $this->version);

        $process = Process::fromShellCommandline('git log -n1 --pretty=%ci HEAD', __DIR__);
        if (0 !== $process->run()) {
            throw new RuntimeException(
                'Can\'t run git log. You must ensure to run compile from composer git repository clone and that git'
                . ' binary is available.'
            );
        }

        $this->versionDate = new DateTime(trim($process->getOutput()));

        $this->versionDate->setTimezone(new DateTimeZone('UTC'));
        $this->writeLn('Version data: '. $this->versionDate->format(DateTimeInterface::ATOM));

        $process = Process::fromShellCommandline('git describe --tags --exact-match HEAD');
        if (0 === $process->run()) {
            $this->version = trim($process->getOutput());
            $this->writeLn('Version is a tag: ' . $this->version);
        } else {
            // get branch-alias defined in composer.json for dev-master (if any)
            $localConfig = $this->projectDirectory . '/composer.json';
            $file = new JsonFile($localConfig);
            $localConfig = $file->read();
            if (isset($localConfig['extra']['branch-alias']['dev-master'])) {
                $this->branchAliasVersion = $localConfig['extra']['branch-alias']['dev-master'];
                $this->writeLn('Branch alias detected: ' . $this->branchAliasVersion);
            }
        }

        $this->writeLn('Phar create');
        $phar = new Phar($pharFile, 0, basename($pharFile));
        $this->writeLn('Phar set signature algorithm');
        $phar->setSignatureAlgorithm(Phar::SHA1);
        $this->writeLn('Phar start buffering');
        $phar->startBuffering();

        $finderSort = static function (SplFileInfo $a, SplFileInfo $b): int {
            return strcmp(str_replace('\\', '/', $a->getRealPath()), str_replace('\\', '/', $b->getRealPath()));
        };

        $this->addFile($phar, new SplFileInfo($this->projectDirectory . '/bootstrap.php'));

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->exclude('var')
            ->in($this->sourceDirectory)
            ->sort($finderSort);

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $this->addFile(
            $phar,
            new SplFileInfo($this->vendorDirectory . '/symfony/console/Resources/bin/hiddeninput.exe'),
            false
        );

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->name('LICENSE')
            ->exclude('Tests')
            ->exclude('tests')
            ->exclude('docs')
            ->in($this->cacheDirectory)
            ->sort($finderSort);

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }
        $this->addFileWhenExists($phar, $this->cacheDirectory . '/composer/installed.json');
        $this->filesystem->remove(__DIR__ . '/../var/cache/vendor');

        $this->addBinFile($phar, $this->projectDirectory . '/bin/' . basename($pharFile, '.phar'));

        // Stubs
        $this->writeLn('Phar set stub');
        $phar->setStub($this->getStub(basename($pharFile)));

        $this->writeLn('Phar stop buffering');
        $phar->stopBuffering();

        $this->addFile($phar, new SplFileInfo($this->projectDirectory . '/LICENSE'), false);

        unset($phar);

        $this->writeLn('Lint all files inside the phar file');
        Linter::lint($pharFile);

        $newPharFileName = $this->renamePharFileAndSetFilePermissions($pharFile);

        $this->filesystem->remove(__DIR__ . '/../var/cache/vendor');
        $this->createSignatureFile($newPharFileName);

        $this->writeLn('Done');
    }

    /**
     * @param string $pharFile
     * @return string
     * @throws IOException
     */
    private function renamePharFileAndSetFilePermissions(string $pharFile): string
    {
        $newPharFileName = dirname($pharFile) . DIRECTORY_SEPARATOR . basename($pharFile, '.phar');
        $this->writeLn('Rename from: ' . $pharFile . ' to: ' . $newPharFileName);
        $this->filesystem->rename($pharFile, $newPharFileName, true);
        $this->writeLn('Set file permissions to 0755');
        $this->filesystem->chmod($newPharFileName, 0755);

        return $newPharFileName;
    }

    /**
     * @param string $pharFileName
     * @return void
     */
    private function createSignatureFile(string $pharFileName): void
    {
        $privateKeyResource = openssl_pkey_get_private('file://' . $this->resourcesDirectory . '/private-key.pem');
        $algorithm = defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'SHA384';
        openssl_sign(file_get_contents($pharFileName), $signature, $privateKeyResource, $algorithm);
        openssl_free_key($privateKeyResource);
        $content = json_encode([
            'sha384' => base64_encode($signature)
        ]);
        $signatureFile = $this->buildDirectory . '/' . basename($pharFileName) . '.sig';
        $this->writeLn('Write signature to file: ' . $signatureFile);
        file_put_contents($signatureFile, $content);
    }

    /**
     * @param SplFileInfo $file
     * @return string
     */
    private function getRelativeFilePath(SplFileInfo $file): string
    {
        $realPath = $file->getRealPath();
        $pathPrefix = $this->projectDirectory . DIRECTORY_SEPARATOR;

        $pos = strpos($realPath, $pathPrefix);
        $relativePath = ($pos !== false) ? substr_replace($realPath, '', $pos, strlen($pathPrefix)) : $realPath;

        return str_replace('\\', '/', $relativePath);
    }

    /**
     * @param Phar $phar
     * @param string $filename
     * @param bool $strip
     * @return void
     */
    private function addFileWhenExists(Phar $phar, string $filename, bool $strip = true): void
    {
        if (file_exists($filename)) {
            $this->addFile($phar, new SplFileInfo($filename), $strip);
        }
    }

    /**
     * @param Phar $phar
     * @param SplFileInfo $file
     * @param bool $strip
     * @return void
     */
    private function addFile(Phar $phar, SplFileInfo $file, bool $strip = true): void
    {
        $this->writeLn('Phar add file: ' . $file->getRealPath());
        $path = $this->getRelativeFilePath($file);
        if (false !== strpos($path, 'var/cache/vendor')) {
            $path = str_replace('var/cache/vendor', 'vendor', $path);
        }
        $content = file_get_contents($file->getRealPath());
        if ($strip) {
            $content = $this->stripWhitespace($content);
        } elseif ($file->getBasename() === 'LICENSE') {
            $content = "\n" . $content . "\n";
        }

        if ($path === 'vendor/symfony/polyfill-php73/Resources/stubs/JsonException.php') {
            $content = <<<EOF
<?php

if (!class_exists('\JsonException')) {

    class JsonException extends Exception {}

}

EOF;
        }

        if ($path === 'src/Application.php') {
            $content = str_replace(
                ['@package_version@', '@package_branch_alias_version@', '@release_date@'],
                [$this->version, $this->branchAliasVersion, $this->versionDate->format('Y-m-d H:i:s')],
                $content
            );
            $content = preg_replace('{SOURCE_VERSION = \'[^\']+\';}', 'SOURCE_VERSION = \'\';', $content);
        }

        $phar->addFromString($path, $content);
    }

    /**
     * @param Phar $phar
     * @param string $filename
     * @return void
     */
    private function addBinFile(Phar $phar, string $filename): void
    {
        $this->writeLn('Phar add: ' . $filename);
        $content = file_get_contents($filename);
        $this->writeLn('Phar remove shebang line from: ' . $filename);
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString($this->getRelativeFilePath(new SplFileInfo($filename)), $content);
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace($source): string
    {
        $this->writeLn('Strip whitespace');
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $output .= str_repeat(PHP_EOL, substr_count($token[1], PHP_EOL));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * @param string $message
     * @return void
     */
    private function writeLn(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * @param string $pharFile
     * @return string
     */
    private function getStub(string $pharFile): string
    {
        $binExecutable = basename($pharFile, '.phar');
        $year = date('Y');
        $stub = <<<EOF
#!/usr/bin/env php
<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) {$year} Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
if (extension_loaded('apc') && filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN) && filter_var(ini_get('apc.cache_by_default'), FILTER_VALIDATE_BOOLEAN)) {
    if (version_compare(phpversion('apc'), '3.0.12', '>=')) {
        ini_set('apc.cache_by_default', 0);
    } else {
        fwrite(STDERR, 'Warning: APC <= 3.0.12 may cause fatal errors when running composer commands.'.PHP_EOL);
        fwrite(STDERR, 'Update APC, or set apc.enable_cli or apc.cache_by_default to 0 in your php.ini.'.PHP_EOL);
    }
}

Phar::mapPhar('{$pharFile}');

EOF;

        // add warning once the phar is older than 60 days
        if (preg_match('{^[a-f0-9]+$}', $this->version)) {
            $warningTime = ((int)$this->versionDate->format('U')) + 60 * 86400;
            $stub .= "define('DEV_WARNING_TIME', $warningTime);\n\n";
        }

        return $stub . <<<EOF
require 'phar://{$pharFile}/bin/{$binExecutable}';

__HALT_COMPILER();

EOF;
    }
}
