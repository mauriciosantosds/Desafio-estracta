<?php

declare(strict_types=1);

namespace ProvaSpider\ValueObject;

use JsonSerializable;

/**
 * Cadastro publicado pela consulta básica da SEFAZ-BA.
 *
 * null representa campo ausente; identificadores (CNPJ, IE, CEP, CNAE) são strings
 * sem máscara para preservar zeros à esquerda.
 *
 * - atividadeEconomica: atividade principal.
 * - regimeApuracao: campo "Forma de pagamento" (ex.: C/CORRENTE FISCAL).
 * - condicao: campo "Condição" (ex.: NORMAL).
 */
final class CadastroContribuinte implements JsonSerializable
{
    /**
     * @param list<AtividadeEconomica> $atividadesSecundarias
     */
    public function __construct(
        public readonly ?string $inscricaoEstadual,
        public readonly ?string $cnpj,
        public readonly ?string $razaoSocial,
        public readonly ?string $nomeFantasia,
        public readonly ?string $situacaoCadastral,
        public readonly ?Endereco $endereco,
        public readonly ?AtividadeEconomica $atividadeEconomica,
        public readonly ?string $regimeApuracao,
        public readonly ?string $naturezaJuridica = null,
        public readonly ?string $condicao = null,
        public readonly array $atividadesSecundarias = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
