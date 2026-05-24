<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests\Middleware;

use Amashukov\HttpClient\Exception\TransportException;
use Amashukov\HttpClient\Middleware\RetryMiddleware;
use Amashukov\HttpClient\Pipeline;
use Amashukov\HttpClient\Tests\Stub\RecordingClient;
use Closure;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(RetryMiddleware::class)]
final class RetryMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    private RequestInterface $request;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->request = $this->factory->createRequest('GET', 'https://example.test/');
    }

    public function testRejectsZeroMaxAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryMiddleware(maxAttempts: 0);
    }

    public function testRejectsNegativeBaseDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryMiddleware(baseDelayMs: -1);
    }

    public function testAcceptsClosureSleeper(): void
    {
        $middleware = new RetryMiddleware(sleeper: static function (int $delayMs): void {});

        self::assertInstanceOf(RetryMiddleware::class, $middleware);
    }

    public function testPassesThroughSuccessOnFirstAttempt(): void
    {
        $inner = new RecordingClient([$this->factory->createResponse(200)]);
        $pipe  = new Pipeline($inner, [new RetryMiddleware(sleeper: $this->noopSleeper())]);

        $response = $pipe->sendRequest($this->request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $inner->recorded());
    }

    public function testRetriesOnConfiguredStatusCode(): void
    {
        $inner = new RecordingClient([
            $this->factory->createResponse(503),
            $this->factory->createResponse(503),
            $this->factory->createResponse(200),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(maxAttempts: 3, retryStatusCodes: [503], baseDelayMs: 0, sleeper: $this->noopSleeper()),
        ]);

        $response = $pipe->sendRequest($this->request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $inner->recorded());
    }

    public function testReturnsLastResponseWhenAllAttemptsExhausted(): void
    {
        $inner = new RecordingClient([
            $this->factory->createResponse(503),
            $this->factory->createResponse(503),
            $this->factory->createResponse(503),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(maxAttempts: 3, retryStatusCodes: [503], baseDelayMs: 0, sleeper: $this->noopSleeper()),
        ]);

        $response = $pipe->sendRequest($this->request);

        self::assertSame(503, $response->getStatusCode());
        self::assertCount(3, $inner->recorded());
    }

    public function testDoesNotRetryNonConfiguredStatusCode(): void
    {
        $inner = new RecordingClient([
            $this->factory->createResponse(400),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(retryStatusCodes: [503], sleeper: $this->noopSleeper()),
        ]);

        $response = $pipe->sendRequest($this->request);

        self::assertSame(400, $response->getStatusCode());
        self::assertCount(1, $inner->recorded());
    }

    public function testRetriesOnNetworkException(): void
    {
        $inner = new RecordingClient([
            new TransportException($this->request, 'connect timeout'),
            new TransportException($this->request, 'connect timeout'),
            $this->factory->createResponse(200),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(maxAttempts: 3, baseDelayMs: 0, sleeper: $this->noopSleeper()),
        ]);

        $response = $pipe->sendRequest($this->request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $inner->recorded());
    }

    public function testRethrowsNetworkExceptionWhenAttemptsExhausted(): void
    {
        $inner = new RecordingClient([
            new TransportException($this->request, 'boom 1'),
            new TransportException($this->request, 'boom 2'),
            new TransportException($this->request, 'final boom'),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(maxAttempts: 3, baseDelayMs: 0, sleeper: $this->noopSleeper()),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('final boom');

        $pipe->sendRequest($this->request);
    }

    public function testInjectedSleeperReceivesExponentialBackoffDelays(): void
    {
        $delays = [];
        $sleeper = static function (int $delayMs) use (&$delays): void {
            $delays[] = $delayMs;
        };

        $inner = new RecordingClient([
            $this->factory->createResponse(503),
            $this->factory->createResponse(503),
            $this->factory->createResponse(503),
            $this->factory->createResponse(200),
        ]);
        $pipe = new Pipeline($inner, [
            new RetryMiddleware(maxAttempts: 4, retryStatusCodes: [503], baseDelayMs: 100, sleeper: $sleeper),
        ]);

        $pipe->sendRequest($this->request);

        self::assertSame([100, 200, 400], $delays);
    }

    /**
     * @return Closure(int): void
     */
    private function noopSleeper(): Closure
    {
        return static function (int $delayMs): void {};
    }
}
