<?php

declare(strict_types=1);

namespace ProvaSpider\Tests\Support;

use ProvaSpider\Http\HttpClient;
use ProvaSpider\Http\HttpRequest;
use ProvaSpider\Http\HttpResponse;
use RuntimeException;
use Throwable;

/**
 * Transporte em memória: devolve (ou lança) as respostas na ordem enfileirada
 * e registra as requisições recebidas.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|Throwable> */
    private array $fila;

    public function __construct(HttpResponse|Throwable ...$respostas)
    {
        $this->fila = array_values($respostas);
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;

        $proxima = array_shift($this->fila)
            ?? throw new RuntimeException('FakeHttpClient: nenhuma resposta enfileirada.');

        if ($proxima instanceof Throwable) {
            throw $proxima;
        }

        return $proxima;
    }
}
