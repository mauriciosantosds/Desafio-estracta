<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\ConsultaException;
use ProvaSpider\Exception\EntradaInvalidaException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\FalhaTransporteException;
use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\Tests\Support\FakeHttpClient;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

function paginaOk(string $fixture = 'cadastro_completo.html', string $contentType = 'text/html'): HttpResponse
{
    return new HttpResponse(200, ['Content-Type' => $contentType], fixture($fixture));
}

function redirecionaPara(string $location): HttpResponse
{
    return new HttpResponse(302, ['Location' => $location], '<head><title>Object moved</title></head>');
}

it('envia o formulário de CNPJ por POST para result.asp', function () {
    $http = new FakeHttpClient(paginaOk());

    (new SefazBaSpider($http))->consultar(new Consulta('00.123.456/0001-91'));

    expect($http->requests)->toHaveCount(1);
    $request = $http->requests[0];

    expect($request->method)->toBe('POST')
        ->and($request->url)->toBe(SefazBaSpider::URL_RESULTADO)
        ->and($request->headers['Referer'])->toBe(SefazBaSpider::URL)
        ->and($request->form)->toBe([
            'sefp' => '1',
            'estado' => 'BA',
            'CGC' => '00123456000191',
            'B1' => 'CNPJ  ->',
            'CPF' => '',
            'IE' => '',
        ]);
});

it('não depende de sessão: consulta em uma única requisição, sem cookie', function () {
    $http = new FakeHttpClient(paginaOk());

    (new SefazBaSpider($http))->consultar(new Consulta('00123456000191'));

    expect($http->requests)->toHaveCount(1)
        ->and(array_change_key_case($http->requests[0]->headers))->not->toHaveKey('cookie');
});

it('retorna o cadastro extraído da página', function () {
    $cadastro = (new SefazBaSpider(new FakeHttpClient(paginaOk())))->consultar(new Consulta('00123456000191'));

    expect($cadastro)->toBeInstanceOf(CadastroContribuinte::class)
        ->and($cadastro->cnpj)->toBe('00123456000191')
        ->and($cadastro->inscricaoEstadual)->toBe('001234567')
        ->and($cadastro->razaoSocial)->toBe('COMÉRCIO FICTÍCIO DE AÇAÍ LTDA');
});

it('decodifica página em ISO-8859-1', function () {
    $http = new FakeHttpClient(paginaOk('cadastro_campos_ausentes_latin1.html', 'text/html'));

    $cadastro = (new SefazBaSpider($http))->consultar(new Consulta('01000000000100'));

    expect($cadastro->razaoSocial)->toBe('JOSÉ DA CONCEIÇÃO FICTÍCIO ME')
        ->and($cadastro->nomeFantasia)->toBeNull();
});

it('lança CadastroNaoEncontradoException quando o portal redireciona para consulta_vazia.htm', function () {
    $http = new FakeHttpClient(redirecionaPara('consulta_vazia.htm'));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(CadastroNaoEncontradoException::class, '00123456000191');

    // O destino responde 404 no portal real; não deve ser seguido.
    expect($http->requests)->toHaveCount(1);
});

it('lança EntradaInvalidaException quando o portal rejeita o CNPJ', function (string $location) {
    $http = new FakeHttpClient(redirecionaPara($location));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000199')))
        ->toThrow(EntradaInvalidaException::class);
})->with(['cnpj_incorreto.htm', 'param_errado.htm', '/scripts/cadastro/cadastroBa/CNPJ_INCORRETO.HTM']);

it('segue redirecionamento desconhecido com GET na URL resolvida', function () {
    $http = new FakeHttpClient(redirecionaPara('outra/pagina.asp?x=1'), paginaOk());

    $cadastro = (new SefazBaSpider($http))->consultar(new Consulta('00123456000191'));

    expect($cadastro->cnpj)->toBe('00123456000191')
        ->and($http->requests[1]->method)->toBe('GET')
        ->and($http->requests[1]->url)->toBe('https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/outra/pagina.asp?x=1');
});

it('limita a quantidade de redirecionamentos', function () {
    $respostas = array_fill(0, SefazBaSpider::MAX_REDIRECIONAMENTOS + 1, redirecionaPara('loop.asp'));
    $http = new FakeHttpClient(...$respostas);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(RespostaInesperadaException::class, 'Limite de 3 redirecionamentos');

    expect($http->requests)->toHaveCount(SefazBaSpider::MAX_REDIRECIONAMENTOS + 1);
});

it('rejeita redirecionamento sem Location', function () {
    $http = new FakeHttpClient(new HttpResponse(302));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(RespostaInesperadaException::class);
});

it('lança FalhaHttpException com o status de erro', function (int $status) {
    $http = new FakeHttpClient(new HttpResponse($status, [], 'erro'));

    try {
        (new SefazBaSpider($http))->consultar(new Consulta('00123456000191'));
        $this->fail('Deveria ter lançado FalhaHttpException.');
    } catch (FalhaHttpException $e) {
        expect($e->status)->toBe($status);
    }
})->with([403, 404, 500, 503]);

it('propaga a falha de transporte', function () {
    $http = new FakeHttpClient(new FalhaTransporteException('cURL error 28: timeout'));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(FalhaTransporteException::class, 'timeout');
});

it('lança RespostaInesperadaException para HTML que não é o resultado', function () {
    $http = new FakeHttpClient(paginaOk('pagina_inesperada.html'));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(RespostaInesperadaException::class);
});

it('rejeita cadastro de outro CNPJ', function () {
    $http = new FakeHttpClient(paginaOk());

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('11222333000181')))
        ->toThrow(RespostaInesperadaException::class, '00123456000191');
});

it('não apresenta o CNPJ da entrada quando ele não aparece no resultado', function () {
    $http = new FakeHttpClient(paginaOk('cadastro_sem_cnpj.html'));

    $cadastro = (new SefazBaSpider($http))->consultar(new Consulta('00000000000000'));

    expect($cadastro->cnpj)->toBeNull()
        ->and($cadastro->inscricaoEstadual)->toBe('012345678');
});

it('todas as falhas da consulta são ConsultaException', function () {
    $http = new FakeHttpClient(redirecionaPara('consulta_vazia.htm'));

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('00123456000191')))
        ->toThrow(ConsultaException::class);
});
