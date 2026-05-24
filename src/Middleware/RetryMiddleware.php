<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Middleware;

use Amashukov\HttpClient\MiddlewareInterface;
use Closure;
use InvalidArgumentException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final readonly class RetryMiddleware implements MiddlewareInterface
{
    /**
     * @param list<int>                  $retryStatusCodes responses whose status code triggers a retry
     * @param null|Closure(int): void    $sleeper          micro-injectable for testing (default usleep)
     */
    public function __construct(
        private int $maxAttempts = 3,
        private array $retryStatusCodes = [429, 502, 503, 504],
        private int $baseDelayMs = 200,
        private ?Closure $sleeper = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }
        if ($baseDelayMs < 0) {
            throw new InvalidArgumentException('baseDelayMs must be non-negative.');
        }
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $lastResponse = null;
        $lastNetError = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            try {
                $response = $next($request);
            } catch (NetworkExceptionInterface $exception) {
                $lastNetError = $exception;
                if ($attempt === $this->maxAttempts) {
                    throw $exception;
                }
                $this->sleep($this->backoffMsFor($attempt));

                continue;
            }

            if (!\in_array($response->getStatusCode(), $this->retryStatusCodes, true)) {
                return $response;
            }

            $lastResponse = $response;
            if ($attempt === $this->maxAttempts) {
                return $response;
            }

            $this->sleep($this->backoffMsFor($attempt));
        }

        if (null !== $lastResponse) {
            return $lastResponse;
        }
        if ($lastNetError instanceof NetworkExceptionInterface) {
            throw $lastNetError;
        }

        throw new RuntimeException('RetryMiddleware exited without a response (unreachable).');
    }

    private function backoffMsFor(int $attempt): int
    {
        return $this->baseDelayMs * (2 ** ($attempt - 1));
    }

    private function sleep(int $delayMs): void
    {
        if (0 === $delayMs) {
            return;
        }
        if ($this->sleeper instanceof Closure) {
            ($this->sleeper)($delayMs);

            return;
        }
        usleep($delayMs * 1000);
    }
}
