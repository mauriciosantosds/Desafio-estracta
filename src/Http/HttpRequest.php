<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $form Campos enviados como application/x-www-form-urlencoded.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly array $form = [],
    ) {
    }
}
