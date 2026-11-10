<?php

declare(strict_types=1);

namespace ProvaSpider\ValueObject;

use JsonSerializable;

/**
 * Endereço do estabelecimento como publicado pelo portal. CEP sem máscara.
 */
final class Endereco implements JsonSerializable
{
    public function __construct(
        public readonly ?string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $bairro,
        public readonly ?string $cep,
        public readonly ?string $municipio,
        public readonly ?string $uf,
    ) {
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
