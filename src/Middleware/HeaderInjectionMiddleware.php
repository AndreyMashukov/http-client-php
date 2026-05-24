<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Middleware;

use Amashukov\HttpClient\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class HeaderInjectionMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, string> $headers headers set unconditionally on every outbound request (replace any caller-supplied value)
     */
    public function __construct(
        private array $headers,
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $next($request);
    }
}
