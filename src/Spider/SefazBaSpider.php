<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use ProvaSpider\Contract\Spider;
use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\EntradaInvalidaException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Extraction\CadastroMapper;
use ProvaSpider\Extraction\HtmlDecoder;
use ProvaSpider\Extraction\PaginaCadastroParser;
use ProvaSpider\Http\GuzzleHttpClient;
use ProvaSpider\Http\HttpClient;
use ProvaSpider\Http\HttpRequest;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Http\RetryingHttpClient;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

/**
 * Consulta básica ao cadastro de contribuintes do ICMS da Bahia, por CNPJ.
 *
 * O formulário de consultaBa.asp faz POST para result.asp; não há etapa intermediária
 * nem dependência de cookie de sessão. O portal sinaliza "sem cadastro" e "CNPJ
 * incorreto" com 302 para páginas que hoje respondem 404, por isso os redirecionamentos
 * são interpretados aqui, antes de qualquer tentativa de segui-los.
 */
final class SefazBaSpider implements Spider
{
    public const URL = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp';
    public const URL_RESULTADO = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/result.asp';
    public const MAX_REDIRECIONAMENTOS = 3;

    private const USER_AGENT = 'Mozilla/5.0 (compatible; DesafioEstracta/1.0)';

    /** Destinos de redirecionamento conhecidos de result.asp. */
    private const REDIRECIONAMENTOS = [
        'consulta_vazia.htm' => CadastroNaoEncontradoException::class,
        'cnpj_incorreto.htm' => EntradaInvalidaException::class,
        'param_errado.htm' => EntradaInvalidaException::class,
    ];

    private const MENSAGENS = [
        'consulta_vazia.htm' => 'Nenhum cadastro encontrado na SEFAZ-BA para o CNPJ %s.',
        'cnpj_incorreto.htm' => 'A SEFAZ-BA considerou o CNPJ %s incorreto (verifique os dígitos verificadores).',
        'param_errado.htm' => 'A SEFAZ-BA rejeitou os parâmetros da consulta para o CNPJ %s.',
    ];

    private readonly HttpClient $http;

    public function __construct(
        ?HttpClient $http = null,
        private readonly PaginaCadastroParser $parser = new PaginaCadastroParser(),
        private readonly CadastroMapper $mapper = new CadastroMapper(),
    ) {
        $this->http = $http ?? new RetryingHttpClient(new GuzzleHttpClient());
    }

    public function consultar(Consulta $consulta): CadastroContribuinte
    {
        $request = new HttpRequest('POST', self::URL_RESULTADO, [
            'User-Agent' => self::USER_AGENT,
            'Referer' => self::URL,
        ], [
            'sefp' => '1',
            'estado' => 'BA',
            'CGC' => $consulta->cnpj,
            'B1' => 'CNPJ  ->',
            'CPF' => '',
            'IE' => '',
        ]);

        $response = $this->enviarSeguindoRedirecionamentos($request, $consulta);

        if ($response->status >= 400) {
            throw new FalhaHttpException($response->status, $request->url);
        }

        if ($response->status !== 200) {
            throw new RespostaInesperadaException(sprintf('Status HTTP inesperado: %d.', $response->status));
        }

        $html = HtmlDecoder::paraUtf8($response->body, $response->header('Content-Type'));
        $cadastro = $this->mapper->mapear($this->parser->parse($html));

        if ($cadastro->cnpj !== null && $cadastro->cnpj !== $consulta->cnpj) {
            throw new RespostaInesperadaException(sprintf(
                'O portal devolveu o cadastro do CNPJ %s para a consulta do CNPJ %s.',
                $cadastro->cnpj,
                $consulta->cnpj,
            ));
        }

        return $cadastro;
    }

    private function enviarSeguindoRedirecionamentos(HttpRequest $request, Consulta $consulta): HttpResponse
    {
        for ($saltos = 0; ; $saltos++) {
            $response = $this->http->send($request);

            if (!$response->isRedirect()) {
                return $response;
            }

            $location = $response->header('Location');
            if ($location === null || $location === '') {
                throw new RespostaInesperadaException('Redirecionamento sem cabeçalho Location.');
            }

            $destino = strtolower(basename((string) parse_url($location, PHP_URL_PATH)));
            if (isset(self::REDIRECIONAMENTOS[$destino])) {
                $classe = self::REDIRECIONAMENTOS[$destino];
                throw new $classe(sprintf(self::MENSAGENS[$destino], $consulta->cnpj));
            }

            if ($saltos >= self::MAX_REDIRECIONAMENTOS) {
                throw new RespostaInesperadaException(sprintf(
                    'Limite de %d redirecionamentos excedido (último destino: %s).',
                    self::MAX_REDIRECIONAMENTOS,
                    $location,
                ));
            }

            $url = (string) UriResolver::resolve(new Uri($request->url), new Uri($location));
            $request = new HttpRequest('GET', $url, $request->headers);
        }
    }
}
