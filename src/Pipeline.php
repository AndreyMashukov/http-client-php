<?php

declare(strict_types=1);

namespace Amashukov\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Pipeline implements ClientInterface
{
    /**
     * @param list<MiddlewareInterface> $middlewares applied in the listed order (first wraps the outermost)
     */
    public function __construct(
        private ClientInterface $inner,
        private array $middlewares = [],
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $terminal = fn(RequestInterface $r): ResponseInterface => $this->inner->sendRequest($r);

        $next = $terminal;
        for ($i = \count($this->middlewares) - 1; $i >= 0; --$i) {
            $middleware = $this->middlewares[$i];
            $current    = $next;
            $next       = static fn(RequestInterface $r): ResponseInterface => $middleware->handle($r, $current);
        }

        return $next($request);
    }
}
