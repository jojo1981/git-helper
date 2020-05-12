<?php declare(strict_types=1);
/*
 * This file is part of the jojo1981/git-helper package
 *
 * Copyright (c) 2020 Joost Nijhuis <jnijhuis81@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed in the root of the source code
 */
namespace Jojo1981\GitTag\SelfUpdate\Strategy;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;
use function base64_decode;
use function defined;
use function file_get_contents;
use function Humbug\get_contents;
use function implode;
use function json_decode;
use function openssl_free_key;
use function openssl_pkey_get_public;
use function openssl_verify;
use function sprintf;

/**
 * @package Jojo1981\GitTag\SelfUpdate\Strategy
 */
class GithubStrategy implements StrategyInterface
{
    /** @var string */
    private const PUBLIC_KEY = <<<EOF
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA1cXFM1GifbPJaqe56Wn+
EusB3g9P7USmIbs3+nad4m0ZMmJjbUx5sdVYibaOy4CbHFh5f2A5zKZZnhuocPv7
ClLxxiM300J42Xo8ztnEw06lH4rGWh6Z1ZH1pxPSGIw6PnKx2t+4gjG4Dp+VXjk1
C9gUm4Wgmmn1qZ8NyDf0XYonB+HANTcphVQ7dIKnFzi67rdfNtQl72LTng/WmtvB
kJWHcIqK2KDcj/5zCVlaFeIQ5m89RdvThU26ZqFKWf+hi1SkJ2MmNsVDSGbcFs6X
r5kftN9tbcwxr2DG704r+JAqM0/B+lLRfwJX2pndu7TAFRHoaNtJgG56sBnBbYiG
318EtPWpqwaQtAs7PE9PqUsEooHoX37WeMOyKt8wT7rbrjiD3ZhtMplOHA9OeiB9
LfKk/BvnNU2c4Y8xEsDbL/Zqrmo8UDMKzf8v4RgwTGpYcQuGx0PXstxYjUpiIim3
xNeYAodvCET+x64Jb3clBGaoUMRUwHG0sGTCwmQiKb+e5Xz0w8zsFWSJh/wlDJEQ
yadgrDWQhQzCjwkT310QS9Rp+PBiCmZuJbQpszi+xI8WgEGFgFt0fAb48sIT+Muf
l5TC5bgL7ut/0RHUPklsh89jakx/qmtp0LzRu28YHEYu00+hesYTzEB1e23eWptN
dPzy0h4BJDO5qUlBjj4W3FMCAwEAAQ==
-----END PUBLIC KEY-----
EOF;

    /** @var string */
    private $username;

    /** @var string */
    private $repository;

    /** @var string */
    private $localVersion;

    /** @var ClientInterface */
    private $client;

    /** @var stdCLass */
    private $release;

    /**
     * @param string $username
     * @param string $repository
     * @param string $localVersion
     */
    public function __construct(string $username, string $repository, string $localVersion)
    {
        $this->username = $username;
        $this->repository = $repository;
        $this->localVersion = $localVersion;
    }

    /**
     * @param Updater $updater
     * @return void
     * @throws RuntimeException
     * @throws HttpRequestException
     */
    public function download(Updater $updater): void
    {
        $release = $this->getLatestRelease();
        $signature = null;
        $tempPharFile = null;
        foreach ($release->assets ?? [] as $asset) {
            if ('git-helper.sig' === $asset->name) {
                $result = $this->getContent($updater, $asset->browser_download_url);
                $signature = base64_decode(json_decode(file_get_contents($result), true)['sha384']);
            }
            if ('git-helper' === $asset->name) {
                file_put_contents(
                    $tempPharFile = $updater->getTempPharFile(),
                    $this->getContent($updater, $asset->browser_download_url)
                );
            }
        }

        if (null === $signature || null === $tempPharFile) {
            $messages = [];
            if (null === $signature) {
                $messages[] = 'Could not download signature file: `git-helper.sig`.';
            }
            if (null === $tempPharFile) {
                $messages[] = 'Could not download binary file: `git-helper`.';
            }
            throw new HttpRequestException(implode(PHP_EOL, $messages));
        }

        $algorithm = defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'SHA384';
        $publicKeyResource = openssl_pkey_get_public(self::PUBLIC_KEY);
        $verified = 1 === openssl_verify(file_get_contents($tempPharFile), $signature, $publicKeyResource, $algorithm);
        openssl_free_key($publicKeyResource);
        if (!$verified) {
            throw new RuntimeException(
                'The phar signature did not match the file you downloaded, this means that the phar file is'
                . ' corrupt/has been modified'
            );
        }

        $this->release = null;
    }

    /**
     * @param Updater $updater
     * @return string
     * @throws HttpRequestException
     */
    public function getCurrentRemoteVersion(Updater $updater): string
    {
        return $this->getLatestRelease()->name;
    }

    /**
     * @param Updater $updater
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater): string
    {
        return $this->localVersion;
    }

    /**
     * @return stdClass
     * @throws HttpRequestException
     */
    private function getLatestRelease(): stdClass
    {
        if (null === $this->release) {
            $uri = sprintf('/repos/%s/%s/releases/latest', $this->username, $this->repository);
            $headers = ['Accept' => 'application/vnd.github.v3+json'];
            $errorMessage = sprintf('Request to URL failed: %s', $uri);

            try {
                $response = $this->getClient()->request('GET', $uri, ['headers' => $headers]);
                if (200 !== $response->getStatusCode()) {
                    throw new HttpRequestException($errorMessage);
                }
                $this->release = json_decode((string)$response->getBody(), false);
            } catch (Throwable $exception) {
                throw new HttpRequestException($errorMessage, 0, $exception);
            }
        }

        return $this->release;
    }

    /**
     * @return ClientInterface
     * @throws InvalidArgumentException
     */
    private function getClient(): ClientInterface
    {
        if (null === $this->client) {
            $this->client = new Client([
                'base_uri' => 'https://api.github.com',
                'timeout' => 2.0,
                'http_errors' => false
            ]);
        }

        return $this->client;
    }

    /**
     * @param Updater $updater
     * @param string $url
     * @return string
     * @throws HttpRequestException
     */
    private function getContent(Updater $updater, string $url): string
    {
        set_error_handler(array($updater, 'throwHttpRequestException'));
        $result = get_contents($url);
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s', $url
            ));
        }

        return $result;
    }
}
