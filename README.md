# http-client-php

A small HTTP client backed by `ext-curl`, with a middleware pipeline (retry on selected status codes, header injection, request/response logging).

Designed as a zero-dependency foundation for typed JSON-RPC clients — no PSR-18, no PSR-7, no Guzzle.

## Status

Pre-1.0. Public API may change before the 1.0 tag.

## Requirements

- PHP 8.3+
- `ext-curl`

No composer dependencies.

## License

MIT License.
