<?php

declare(strict_types=1);

namespace Amashukov\HttpClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * Wrap a single PSR-7 request. Call `$next($request)` exactly once
     * (or zero times if short-circuiting with a synthetic response).
     *
     * @param callable(RequestInterface): ResponseInterface $next
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}
