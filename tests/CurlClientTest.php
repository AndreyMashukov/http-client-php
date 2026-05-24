<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests;

use Amashukov\HttpClient\CurlClient;
use Amashukov\HttpClient\Exception\TransportException;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurlClient::class)]
final class CurlClientTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testConstructorRejectsZeroTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('timeoutSeconds must be at least 1');

        new CurlClient($this->factory, $this->factory, timeoutSeconds: 0);
    }

    public function testConstructorRejectsZeroConnectTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('connectTimeoutSeconds must be at least 1');

        new CurlClient($this->factory, $this->factory, connectTimeoutSeconds: 0);
    }

    public function testConstructorRejectsNegativeMaxRedirects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRedirects must be non-negative');

        new CurlClient($this->factory, $this->factory, maxRedirects: -1);
    }

    public function testSendThrowsTransportExceptionForUnresolvableHost(): void
    {
        $client  = new CurlClient($this->factory, $this->factory, timeoutSeconds: 2, connectTimeoutSeconds: 2);
        $request = $this->factory->createRequest('GET', 'http://this-host-must-not-exist-amashukov-http-client.invalid/');

        try {
            $client->sendRequest($request);
            self::fail('CurlClient must throw TransportException on DNS failure');
        } catch (TransportException $exception) {
            self::assertSame($request, $exception->getRequest());
            self::assertMatchesRegularExpression('/curl error #\d+/', $exception->getMessage());
        }
    }

    public function testSendThrowsTransportExceptionForUnreachableLocalPort(): void
    {
        $client  = new CurlClient($this->factory, $this->factory, timeoutSeconds: 2, connectTimeoutSeconds: 2);
        $request = $this->factory->createRequest('GET', 'http://127.0.0.1:1/');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/curl error #\d+/');

        $client->sendRequest($request);
    }
}
