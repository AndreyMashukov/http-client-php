<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests\Middleware;

use Amashukov\HttpClient\Middleware\HeaderInjectionMiddleware;
use Amashukov\HttpClient\Pipeline;
use Amashukov\HttpClient\Tests\Stub\RecordingClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderInjectionMiddleware::class)]
final class HeaderInjectionMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testAddsConfiguredHeadersWhenAbsent(): void
    {
        $inner = new RecordingClient([$this->factory->createResponse(200)]);
        $pipe  = new Pipeline($inner, [new HeaderInjectionMiddleware(['X-Api-Key' => 'secret'])]);

        $pipe->sendRequest($this->factory->createRequest('GET', 'https://example.test/'));

        self::assertSame(['secret'], $inner->recorded()[0]->getHeader('X-Api-Key'));
    }

    public function testOverridesCallerHeaderWithConfiguredValue(): void
    {
        $inner = new RecordingClient([$this->factory->createResponse(200)]);
        $pipe  = new Pipeline($inner, [new HeaderInjectionMiddleware(['X-Api-Key' => 'injected'])]);

        $request = $this->factory->createRequest('GET', 'https://example.test/')
            ->withHeader('X-Api-Key', 'caller-supplied');
        $pipe->sendRequest($request);

        self::assertSame(['injected'], $inner->recorded()[0]->getHeader('X-Api-Key'));
    }

    public function testPreservesCallerHeadersThatAreNotConfigured(): void
    {
        $inner = new RecordingClient([$this->factory->createResponse(200)]);
        $pipe  = new Pipeline($inner, [new HeaderInjectionMiddleware(['X-Api-Key' => 'k'])]);

        $request = $this->factory->createRequest('GET', 'https://example.test/')
            ->withHeader('Accept', 'application/json');
        $pipe->sendRequest($request);

        self::assertSame(['application/json'], $inner->recorded()[0]->getHeader('Accept'));
        self::assertSame(['k'], $inner->recorded()[0]->getHeader('X-Api-Key'));
    }
}
