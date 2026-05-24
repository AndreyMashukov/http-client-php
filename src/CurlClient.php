<?php

declare(strict_types=1);

namespace Amashukov\HttpClient;

use Amashukov\HttpClient\Exception\TransportException;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class CurlClient implements ClientInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private int $timeoutSeconds = 30,
        private int $connectTimeoutSeconds = 10,
        private bool $followRedirects = false,
        private int $maxRedirects = 5,
    ) {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('timeoutSeconds must be at least 1.');
        }
        if ($connectTimeoutSeconds < 1) {
            throw new InvalidArgumentException('connectTimeoutSeconds must be at least 1.');
        }
        if ($maxRedirects < 0) {
            throw new InvalidArgumentException('maxRedirects must be non-negative.');
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = curl_init();
        if (false === $handle) {
            throw new TransportException($request, 'curl_init() failed.');
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_URL            => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST  => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => $this->followRedirects,
            CURLOPT_MAXREDIRS      => $this->maxRedirects,
            CURLOPT_HEADERFUNCTION => static function ($_, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ('' === $trimmed || str_starts_with($trimmed, 'HTTP/')) {
                    return \strlen($line);
                }
                $sep = strpos($trimmed, ':');
                if (false === $sep) {
                    return \strlen($line);
                }
                $name  = trim(substr($trimmed, 0, $sep));
                $value = ltrim(substr($trimmed, $sep + 1));
                if (!isset($responseHeaders[$name])) {
                    $responseHeaders[$name] = [];
                }
                $responseHeaders[$name][] = $value;

                return \strlen($line);
            },
        ]);

        $outboundHeaders = [];
        foreach ($request->getHeaders() as $name => $values) {
            $outboundHeaders[] = $name . ': ' . implode(', ', $values);
        }
        if ([] !== $outboundHeaders) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $outboundHeaders);
        }

        $body = (string) $request->getBody();
        if ('' !== $body) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if (false === $responseBody) {
            $errorMessage = curl_error($handle);
            $errorNumber  = curl_errno($handle);

            throw new TransportException($request, sprintf('curl error #%d: %s', $errorNumber, $errorMessage));
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($status <= 0) {
            throw new TransportException($request, 'Could not parse response status code from curl.');
        }
        if (!\is_string($responseBody)) {
            throw new RuntimeException('curl_exec returned a non-string body despite RETURNTRANSFER=true.');
        }

        $response = $this->responseFactory->createResponse($status)
            ->withBody($this->streamFactory->createStream($responseBody));
        foreach ($responseHeaders as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response;
    }
}
