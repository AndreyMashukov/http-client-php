<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests\Stub;

use Amashukov\HttpClient\MiddlewareInterface;
use ArrayObject;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class TracingMiddleware implements MiddlewareInterface
{
    /**
     * @param ArrayObject<int, string> $trace
     */
    public function __construct(
        private string $label,
        private ArrayObject $trace,
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $this->trace->append($this->label . ':before');
        $response = $next($request);
        $this->trace->append($this->label . ':after');

        return $response;
    }
}
