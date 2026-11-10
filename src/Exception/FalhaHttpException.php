<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal respondeu com status HTTP de erro (4xx/5xx).
 */
final class FalhaHttpException extends ConsultaException
{
    public function __construct(public readonly int $status, string $url)
    {
        parent::__construct(sprintf('O portal respondeu com HTTP %d em %s.', $status, $url));
    }
}
