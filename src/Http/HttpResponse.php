<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

/**
 * Resposta crua do transporte: o corpo é mantido em bytes, sem conversão de charset.
 */
final class HttpResponse
{
    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        array $headers = [],
        public readonly string $body = '',
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function header(string $nome): ?string
    {
        return $this->headers[strtolower($nome)] ?? null;
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }
}
