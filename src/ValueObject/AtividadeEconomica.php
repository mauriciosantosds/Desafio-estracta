<?php

declare(strict_types=1);

namespace ProvaSpider\ValueObject;

use JsonSerializable;

/**
 * Atividade econômica (CNAE). O código é string de 7 dígitos para preservar zeros à esquerda.
 */
final class AtividadeEconomica implements JsonSerializable
{
    public function __construct(
        public readonly ?string $codigo,
        public readonly string $descricao,
    ) {
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
