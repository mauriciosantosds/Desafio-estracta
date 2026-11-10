<?php

declare(strict_types=1);

namespace ProvaSpider\Extraction;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ProvaSpider\Exception\RespostaInesperadaException;

/**
 * Lê a página result.asp, onde cada dado é uma célula "<td><b>Rótulo:</b>&nbsp;valor</td>".
 *
 * Os mesmos rótulos (Número, CEP, Município...) se repetem nas seções de endereço de
 * correspondência e do contador, por isso as células são agrupadas pelo cabeçalho de
 * seção e a leitura termina quando começam essas seções.
 *
 * Linhas comentadas no HTML (<!-- ... -->) são ignoradas naturalmente pelo DOM.
 */
final class PaginaCadastroParser
{
    /** Cabeçalhos que abrem uma seção de interesse. */
    private const SECOES = [
        'dados da empresa' => PaginaCadastro::EMPRESA,
        'identificação' => PaginaCadastro::EMPRESA,
        'endereço' => PaginaCadastro::ENDERECO,
        'informações complementares' => PaginaCadastro::COMPLEMENTARES,
    ];

    /** Cabeçalhos a partir dos quais os dados não são mais do estabelecimento. */
    private const FIM = ['endereço de correspondência', 'informações do contador'];

    /** Rótulos sem valor na própria célula, seguidos de linhas com itens. */
    private const LISTAS = ['atividade econômica principal', 'atividade econômica secundária'];

    /**
     * @param string $html HTML já convertido para UTF-8 (ver HtmlDecoder).
     *
     * @throws RespostaInesperadaException
     */
    public function parse(string $html): PaginaCadastro
    {
        $xpath = new DOMXPath($this->carregar($html));

        $campos = [];
        $listas = [];
        $secao = null;
        $listaAtual = null;

        /** @var DOMElement $celula */
        foreach ($xpath->query('//th | //td') ?: [] as $celula) {
            $negrito = $xpath->query('./descendant::b[1]', $celula)?->item(0);
            $textoCelula = Texto::normalizar($celula->textContent);

            if ($negrito === null) {
                if ($listaAtual !== null && $textoCelula !== null) {
                    $listas[$listaAtual][] = $textoCelula;
                }
                continue;
            }

            $rotulo = PaginaCadastro::chave($negrito->textContent);
            $valor = Texto::normalizar(mb_substr(
                $textoCelula ?? '',
                mb_strlen(Texto::normalizar($negrito->textContent) ?? ''),
            ));
            $listaAtual = null;

            if (in_array($rotulo, self::FIM, true)) {
                break;
            }

            if ($valor === null && isset(self::SECOES[$rotulo])) {
                $secao = self::SECOES[$rotulo];
                continue;
            }

            if ($secao === null) {
                continue;
            }

            if ($valor === null && in_array($rotulo, self::LISTAS, true)) {
                $listaAtual = $rotulo;
                $listas[$rotulo] ??= [];
                continue;
            }

            // A primeira ocorrência vale: repetições posteriores não sobrescrevem.
            if (!array_key_exists($rotulo, $campos[$secao] ?? [])) {
                $campos[$secao][$rotulo] = $valor;
            }
        }

        if (!isset($campos[PaginaCadastro::EMPRESA])) {
            throw new RespostaInesperadaException(
                'A página retornada não tem a estrutura esperada do cadastro (seção "Dados da empresa" ausente).'
            );
        }

        return new PaginaCadastro($campos, $listas);
    }

    private function carregar(string $html): DOMDocument
    {
        if (trim($html) === '') {
            throw new RespostaInesperadaException('O portal devolveu uma página vazia.');
        }

        // Converte não-ASCII em entidades numéricas para o libxml não depender do <meta charset>.
        $ascii = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        try {
            $carregou = $dom->loadHTML($ascii, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        if (!$carregou) {
            throw new RespostaInesperadaException('Não foi possível interpretar o HTML retornado pelo portal.');
        }

        return $dom;
    }
}
