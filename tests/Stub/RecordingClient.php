<?php

declare(strict_types=1);

namespace Amashukov\HttpClient\Tests\Stub;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class RecordingClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    private array $recorded = [];

    /**
     * @param list<ResponseInterface|ClientExceptionInterface> $script returned/thrown in send-call order
     */
    public function __construct(private array $script) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->recorded[] = $request;
        $next = array_shift($this->script);
        if (null === $next) {
            throw new RuntimeException('RecordingClient: script exhausted.');
        }
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return list<RequestInterface>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
