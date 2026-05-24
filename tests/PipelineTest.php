<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests;

use Amashukov\HttpClient\Pipeline;
use Amashukov\HttpClient\Tests\Stub\RecordingClient;
use Amashukov\HttpClient\Tests\Stub\ShortCircuitMiddleware;
use Amashukov\HttpClient\Tests\Stub\TracingMiddleware;
use ArrayObject;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
final class PipelineTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testWithoutMiddlewaresDelegatesDirectlyToInner(): void
    {
        $inner    = new RecordingClient([$this->factory->createResponse(200)]);
        $pipeline = new Pipeline($inner);

        $response = $pipeline->sendRequest($this->factory->createRequest('GET', 'https://example.test/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $inner->recorded());
    }

    public function testRunsMiddlewaresInListedOrder(): void
    {
        $trace = new ArrayObject();
        $inner = new RecordingClient([$this->factory->createResponse(200)]);
        $pipeline = new Pipeline($inner, [
            new TracingMiddleware('A', $trace),
            new TracingMiddleware('B', $trace),
        ]);

        $pipeline->sendRequest($this->factory->createRequest('GET', 'https://example.test/'));

        self::assertSame(['A:before', 'B:before', 'B:after', 'A:after'], $trace->getArrayCopy());
    }

    public function testMiddlewareCanShortCircuitWithoutCallingNext(): void
    {
        $inner    = new RecordingClient([$this->factory->createResponse(200)]);
        $pipeline = new Pipeline($inner, [new ShortCircuitMiddleware($this->factory, 418)]);

        $response = $pipeline->sendRequest($this->factory->createRequest('GET', 'https://example.test/'));

        self::assertSame(418, $response->getStatusCode());
        self::assertSame([], $inner->recorded());
    }
}
