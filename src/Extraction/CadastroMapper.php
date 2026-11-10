<?php

declare(strict_types=1);

namespace ProvaSpider\Extraction;

use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\ValueObject\AtividadeEconomica;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Endereco;

/**
 * Mapeia os campos brutos da página para o objeto de valor, aplicando as transformações:
 *
 * - CNPJ, IE e CEP: sem máscara, como string.
 * - IE: o portal acrescenta um sufixo (ex.: "001.027.389 NO"); só o número é mantido.
 * - CNAE: código completado com zeros à esquerda até 7 dígitos, porque o portal
 *   às vezes omite o zero (ex.: "111302" para 0111-3/02).
 */
final class CadastroMapper
{
    private const TAMANHO_CNAE = 7;

    public function mapear(PaginaCadastro $pagina): CadastroContribuinte
    {
        $empresa = PaginaCadastro::EMPRESA;
        $comp = PaginaCadastro::COMPLEMENTARES;

        return new CadastroContribuinte(
            inscricaoEstadual: $this->inscricaoEstadual($pagina->campo($empresa, 'Inscrição Estadual')),
            cnpj: $this->cnpj($pagina->campo($empresa, 'CNPJ')),
            razaoSocial: $pagina->campo($empresa, 'Razão Social'),
            nomeFantasia: $pagina->campo($empresa, 'Nome Fantasia'),
            situacaoCadastral: $pagina->campo($comp, 'Situação Cadastral Vigente'),
            endereco: $this->endereco($pagina),
            atividadeEconomica: $this->atividades($pagina->lista('Atividade Econômica Principal'))[0] ?? null,
            regimeApuracao: $pagina->campo($comp, 'Forma de pagamento'),
            naturezaJuridica: $pagina->campo($empresa, 'Natureza Jurídica'),
            condicao: $pagina->campo($comp, 'Condição'),
            atividadesSecundarias: $this->atividades($pagina->lista('Atividade Econômica Secundária')),
        );
    }

    private function cnpj(?string $valor): ?string
    {
        $cnpj = Texto::apenasDigitos($valor);

        if ($cnpj !== null && strlen($cnpj) !== 14) {
            throw new RespostaInesperadaException(sprintf('CNPJ publicado em formato inesperado: "%s".', $valor));
        }

        return $cnpj;
    }

    private function inscricaoEstadual(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        // "001.027.389 NO" -> "001027389": o primeiro bloco é o número da inscrição.
        return Texto::apenasDigitos(explode(' ', $valor, 2)[0]);
    }

    private function endereco(PaginaCadastro $pagina): ?Endereco
    {
        $s = PaginaCadastro::ENDERECO;

        $endereco = new Endereco(
            logradouro: $pagina->campo($s, 'Logradouro'),
            numero: $pagina->campo($s, 'Número'),
            complemento: $pagina->campo($s, 'Complemento'),
            bairro: $pagina->campo($s, 'Bairro/Distrito'),
            cep: Texto::apenasDigitos($pagina->campo($s, 'CEP')),
            municipio: $pagina->campo($s, 'Município'),
            uf: $pagina->campo($s, 'UF'),
        );

        return array_filter($endereco->jsonSerialize(), fn (?string $v) => $v !== null) === [] ? null : $endereco;
    }

    /**
     * @param list<string> $itens Linhas no formato "2021500 - Descrição".
     *
     * @return list<AtividadeEconomica>
     */
    private function atividades(array $itens): array
    {
        $atividades = [];

        foreach ($itens as $item) {
            if (preg_match('/^(\d+)\s*-\s*(.+)$/u', $item, $m) === 1) {
                $codigo = str_pad($m[1], self::TAMANHO_CNAE, '0', STR_PAD_LEFT);
                $atividade = new AtividadeEconomica($codigo, $m[2]);
            } else {
                $codigo = null;
                $atividade = new AtividadeEconomica(null, $item);
            }

            // O portal às vezes repete a mesma atividade secundária.
            $atividades[($codigo ?? '') . '|' . $atividade->descricao] = $atividade;
        }

        return array_values($atividades);
    }
}
