# amashukov/http-client-php

Tiny zero-dependency PSR-18 HTTP client backed by ext-curl, with a retry / header-injection middleware pipeline.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/http-client-php/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/http-client-php/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/http-client-php/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/http-client-php/actions)
[![Latest Version](https://img.shields.io/packagist/v/amashukov/http-client-php)](https://packagist.org/packages/amashukov/http-client-php)
[![Downloads](https://img.shields.io/packagist/dt/amashukov/http-client-php)](https://packagist.org/packages/amashukov/http-client-php)
[![PHP](https://img.shields.io/packagist/dependency-v/amashukov/http-client-php/php)](https://packagist.org/packages/amashukov/http-client-php)
[![License](https://img.shields.io/packagist/l/amashukov/http-client-php)](LICENSE)
[![Stars](https://img.shields.io/github/stars/AndreyMashukov/http-client-php?style=social)](https://github.com/AndreyMashukov/http-client-php)

A small **PSR-18 HTTP client** backed by `ext-curl`, with a composable **middleware pipeline** (retry on selected status codes, header injection). It speaks PSR-7 requests + responses end-to-end and consumes PSR-17 factories, so any PSR-7 implementation (`nyholm/psr7`, `guzzlehttp/psr7`, `slim/psr7`, …) plugs in as the message layer. The package does **not** ship its own PSR-7 implementation — bring whichever you already use.

## Features

- PSR-18 `ClientInterface` over `ext-curl` — drop-in anywhere a PSR-18 client is expected.
- Composable middleware `Pipeline` (itself a PSR-18 client).
- `RetryMiddleware` — exponential backoff on configurable status codes and network errors.
- `HeaderInjectionMiddleware` — stamp API keys / `User-Agent` / telemetry headers on every request.
- Network failures surface as a PSR-18 `NetworkExceptionInterface` carrying the failed request.
- PSR-7 / PSR-17 consumed via interfaces — bring your own implementation.
- PHPStan level 9 clean, `strict_types`, `final` classes.

## Why amashukov/http-client-php

Guzzle is excellent but heavy — it pulls in `guzzlehttp/psr7`, `guzzlehttp/promises`, and its own promise/handler machinery. When all you need is a PSR-18 client that does retries and header injection, this package is a tiny, zero-runtime-dependency alternative: just `ext-curl` plus the PSR interface packages. You supply the PSR-7 implementation you already have, so there is no duplicate message library in your dependency tree.

## Installation

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

### Built-in middlewares

**`HeaderInjectionMiddleware`** — sets the configured headers on every outbound request, replacing any caller-supplied value for the same name. Useful for API keys, `User-Agent`, telemetry headers.

**`RetryMiddleware`** — retries on configured status codes (default `[429, 502, 503, 504]`) and on any thrown `Psr\Http\Client\NetworkExceptionInterface`. Delay is exponential (`baseDelayMs * 2^(attempt-1)`); `baseDelayMs=0` means immediate retry. Returns the last response when all attempts are exhausted; rethrows the last network exception when every attempt failed at the transport layer. A `$sleeper` callable can be injected to control timing in tests.

## Requirements

- PHP 8.3+
- `ext-curl`
- `psr/http-client` ^1.0
- `psr/http-message` ^2.0
- `psr/http-factory` ^1.1
- A PSR-7 implementation (caller-provided)

### PSR conformance

- **PSR-18** — `CurlClient` and `Pipeline` implement `Psr\Http\Client\ClientInterface`; `composer.json` declares `provide: { psr/http-client-implementation: "1.0" }`.
- **PSR-7** — every public method exchanges `Psr\Http\Message\RequestInterface` / `ResponseInterface`.
- **PSR-17** — `CurlClient` consumes `ResponseFactoryInterface` + `StreamFactoryInterface`; it does not implement those factories itself, leaving the choice of PSR-7 implementation to the caller.

## Related packages

| Package | Tier | Purpose |
|---|---|---|
| [amashukov/toncenter-client-php](https://github.com/AndreyMashukov/toncenter-client-php) | RPC | toncenter v2/v3 API client (uses this client) |
| [amashukov/eth-rpc-client-php](https://github.com/AndreyMashukov/eth-rpc-client-php) | RPC | Ethereum JSON-RPC client (uses this client) |
| [amashukov/ton-php](https://github.com/AndreyMashukov/ton-php) | meta | TON umbrella package |
| [amashukov/eth-php](https://github.com/AndreyMashukov/eth-php) | meta | EVM umbrella package |

## Quality

- PHPStan level 9.
- php-cs-fixer with the `@PER-CS` ruleset.
- GitHub Actions CI on every push.
- PSR-18 conformance exercised in the test suite against a PSR-7 implementation.

## License

MIT.
