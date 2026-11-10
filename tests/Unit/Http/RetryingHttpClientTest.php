<?php

declare(strict_types=1);

use ProvaSpider\Exception\FalhaTransporteException;
use ProvaSpider\Http\HttpRequest;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Http\RetryingHttpClient;
use ProvaSpider\Tests\Support\FakeHttpClient;

beforeEach(function () {
    $this->esperas = [];
    $this->request = new HttpRequest('GET', 'https://exemplo.test/');
});

function retrying(FakeHttpClient $fake, array &$esperas, int $max = 3): RetryingHttpClient
{
    return new RetryingHttpClient($fake, maxTentativas: $max, backoffMs: 100, sleep: function (int $ms) use (&$esperas) {
        $esperas[] = $ms;
    });
}

it('repete falhas de transporte com backoff exponencial até obter resposta', function () {
    $fake = new FakeHttpClient(
        new FalhaTransporteException('timeout'),
        new FalhaTransporteException('timeout'),
        new HttpResponse(200, [], 'ok'),
    );

    $response = retrying($fake, $this->esperas)->send($this->request);

    expect($response->body)->toBe('ok')
        ->and($fake->requests)->toHaveCount(3)
        ->and($this->esperas)->toBe([100, 200]);
});

it('repete status 5xx e devolve a última resposta quando as tentativas se esgotam', function () {
    $fake = new FakeHttpClient(new HttpResponse(503), new HttpResponse(502), new HttpResponse(500));

    $response = retrying($fake, $this->esperas)->send($this->request);

    expect($response->status)->toBe(500)
        ->and($fake->requests)->toHaveCount(3);
});

it('relança a falha de transporte quando as tentativas se esgotam', function () {
    $fake = new FakeHttpClient(new FalhaTransporteException('a'), new FalhaTransporteException('b'));

    expect(fn () => retrying($fake, $this->esperas, max: 2)->send($this->request))
        ->toThrow(FalhaTransporteException::class, 'b');
    expect($fake->requests)->toHaveCount(2);
});

it('não repete respostas que não são recuperáveis', function (int $status) {
    $fake = new FakeHttpClient(new HttpResponse($status));

    $response = retrying($fake, $this->esperas)->send($this->request);

    expect($response->status)->toBe($status)
        ->and($fake->requests)->toHaveCount(1)
        ->and($this->esperas)->toBe([]);
})->with([200, 302, 404]);
