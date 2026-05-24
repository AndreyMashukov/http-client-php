# http-client-php

A small PSR-18 HTTP client backed by `ext-curl`, with a middleware pipeline (retry on selected status codes, header injection). Speaks PSR-7 requests + responses end-to-end, so any PSR-7 implementation (`nyholm/psr7`, `guzzlehttp/psr7`, `slim/psr7`, …) plugs in as the factory layer.

The package does **not** ship its own PSR-7 implementation — bring whichever you already use. Tests in this package use `nyholm/psr7`.

## Install

```bash
composer require amashukov/http-client-php nyholm/psr7
```

(Substitute `nyholm/psr7` for the PSR-7 implementation of your choice.)

## Usage

### Send a request

```php
use Amashukov\HttpClient\CurlClient;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory = new Psr17Factory();
$client  = new CurlClient($factory, $factory);

$request  = $factory->createRequest('GET', 'https://api.example.test/data');
$response = $client->sendRequest($request);

echo $response->getStatusCode();          // 200
echo (string) $response->getBody();       // raw body
```

`CurlClient` implements `Psr\Http\Client\ClientInterface`; it is a drop-in replacement anywhere a PSR-18 client is expected. The constructor takes a `Psr\Http\Message\ResponseFactoryInterface` and a `Psr\Http\Message\StreamFactoryInterface` so the caller controls which PSR-7 implementation backs the returned `ResponseInterface`.

Network failures (DNS, connect timeout, TLS handshake, …) surface as `Amashukov\HttpClient\Exception\TransportException`, which implements `Psr\Http\Client\NetworkExceptionInterface` (and therefore `ClientExceptionInterface`); `getRequest()` returns the request that failed.

### Add middlewares

`Pipeline` wraps any PSR-18 client with an ordered list of middlewares, and is itself a PSR-18 client.

```php
use Amashukov\HttpClient\Pipeline;
use Amashukov\HttpClient\Middleware\HeaderInjectionMiddleware;
use Amashukov\HttpClient\Middleware\RetryMiddleware;

$client = new Pipeline(
    new CurlClient($factory, $factory),
    [
        new HeaderInjectionMiddleware(['X-Api-Key' => $apiKey]),
        new RetryMiddleware(
            maxAttempts: 3,
            retryStatusCodes: [429, 502, 503, 504],
            baseDelayMs: 200,
        ),
    ],
);

$response = $client->sendRequest($request);
```

### Writing your own middleware

Implement `Amashukov\HttpClient\MiddlewareInterface`:

```php
use Amashukov\HttpClient\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $start    = microtime(true);
        $response = $next($request);
        printf("%s %s -> %d (%.0fms)\n",
            $request->getMethod(),
            $request->getUri(),
            $response->getStatusCode(),
            (microtime(true) - $start) * 1000,
        );

        return $response;
    }
}
```

Call `$next($request)` exactly once for normal flow, or zero times to short-circuit with a synthetic response.

## Built-in middlewares

### `HeaderInjectionMiddleware`

Sets the configured headers on every outbound request, replacing any caller-supplied value for the same name. Useful for API keys, `User-Agent`, telemetry headers.

### `RetryMiddleware`

Retries on configured status codes (default `[429, 502, 503, 504]`) and on any thrown `Psr\Http\Client\NetworkExceptionInterface`. Delay is exponential (`baseDelayMs * 2^(attempt-1)`); `baseDelayMs=0` means immediate retry. Returns the last response when all attempts are exhausted; rethrows the last network exception when every attempt failed at the transport layer. A `$sleeper` callable can be injected to control timing in tests.

## Requirements

- PHP 8.3+
- `ext-curl`
- `psr/http-client` ^1.0
- `psr/http-message` ^2.0
- `psr/http-factory` ^1.1
- A PSR-7 implementation (caller-provided)

## PSR conformance

- **PSR-18** — `CurlClient` and `Pipeline` implement `Psr\Http\Client\ClientInterface`; `composer.json` declares `provide: { psr/http-client-implementation: "1.0" }`.
- **PSR-7** — every public method exchanges `Psr\Http\Message\RequestInterface` / `ResponseInterface`.
- **PSR-17** — `CurlClient` consumes `ResponseFactoryInterface` + `StreamFactoryInterface`; it does not implement those factories itself, leaving the choice of PSR-7 implementation to the caller.

## License

MIT License.
