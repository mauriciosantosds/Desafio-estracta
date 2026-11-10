<?php

declare(strict_types=1);

namespace ProvaSpider\Extraction;

/**
 * Campos brutos (já normalizados quanto a espaços) da página de resultado,
 * agrupados pela seção onde aparecem.
 */
final class PaginaCadastro
{
    public const EMPRESA = 'empresa';
    public const ENDERECO = 'endereco';
    public const COMPLEMENTARES = 'complementares';

    /**
     * @param array<string, array<string, string|null>> $campos seção => rótulo => valor
     * @param array<string, list<string>> $listas rótulo => itens listados abaixo dele
     */
    public function __construct(
        private readonly array $campos,
        private readonly array $listas,
    ) {
    }

    public function campo(string $secao, string $rotulo): ?string
    {
        return $this->campos[$secao][self::chave($rotulo)] ?? null;
    }

    public function temCampo(string $secao, string $rotulo): bool
    {
        return array_key_exists(self::chave($rotulo), $this->campos[$secao] ?? []);
    }

    /** @return list<string> */
    public function lista(string $rotulo): array
    {
        return $this->listas[self::chave($rotulo)] ?? [];
    }

    /**
     * Rótulos são comparados sem dois-pontos finais e sem diferenciar maiúsculas.
     */
    public static function chave(string $rotulo): string
    {
        return mb_strtolower(rtrim(Texto::normalizar($rotulo) ?? '', ': '));
    }
}
