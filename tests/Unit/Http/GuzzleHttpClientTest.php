<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ProvaSpider\Exception\FalhaTransporteException;
use ProvaSpider\Http\GuzzleHttpClient;
use ProvaSpider\Http\HttpRequest;

function guzzleComMock(MockHandler $mock): GuzzleHttpClient
{
    return new GuzzleHttpClient(new Client(['handler' => HandlerStack::create($mock)]), timeout: 7.0, connectTimeout: 3.0);
}

it('envia o formulário como x-www-form-urlencoded com timeout e sem seguir redirecionamentos', function () {
    $mock = new MockHandler([new Response(200, [], 'ok')]);

    guzzleComMock($mock)->send(new HttpRequest('POST', 'https://exemplo.test/result.asp', ['Referer' => 'https://exemplo.test/'], [
        'CGC' => '00123456000100',
        'B1' => 'CNPJ  ->',
    ]));

    $request = $mock->getLastRequest();
    $options = $mock->getLastOptions();

    expect($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($request->getHeaderLine('Referer'))->toBe('https://exemplo.test/')
        ->and((string) $request->getBody())->toBe('CGC=00123456000100&B1=CNPJ++-%3E')
        ->and($options['timeout'])->toBe(7.0)
        ->and($options['connect_timeout'])->toBe(3.0)
        ->and($options['allow_redirects'])->toBeFalse();
});

it('devolve redirecionamentos e status de erro sem lançar exceção', function (int $status) {
    $mock = new MockHandler([new Response($status, ['Location' => 'destino.htm'], '')]);

    $response = guzzleComMock($mock)->send(new HttpRequest('GET', 'https://exemplo.test/'));

    expect($response->status)->toBe($status)
        ->and($response->header('location'))->toBe('destino.htm');
})->with([302, 404, 503]);

it('preserva o corpo em bytes, sem converter charset', function () {
    $latin1 = "<p>Raz\xE3o</p>";
    $mock = new MockHandler([new Response(200, ['Content-Type' => 'text/html; charset=ISO-8859-1'], $latin1)]);

    $response = guzzleComMock($mock)->send(new HttpRequest('GET', 'https://exemplo.test/'));

    expect($response->body)->toBe($latin1)
        ->and($response->header('Content-Type'))->toBe('text/html; charset=ISO-8859-1');
});

it('converte falha de conexão em FalhaTransporteException', function () {
    $mock = new MockHandler([
        new ConnectException('cURL error 28: Operation timed out', new Request('GET', 'https://exemplo.test/')),
    ]);

    expect(fn () => guzzleComMock($mock)->send(new HttpRequest('GET', 'https://exemplo.test/')))
        ->toThrow(FalhaTransporteException::class, 'Operation timed out');
});
