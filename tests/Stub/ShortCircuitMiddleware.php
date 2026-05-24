<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests\Stub;

use Amashukov\HttpClient\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private int $status,
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        return $this->responseFactory->createResponse($this->status);
    }
}
