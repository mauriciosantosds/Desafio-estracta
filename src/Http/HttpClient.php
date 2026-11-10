<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use ProvaSpider\Exception\FalhaTransporteException;

/**
 * Transporte HTTP substituível.
 *
 * Implementações não seguem redirecionamentos nem lançam exceção por status HTTP:
 * quem decide o que fazer com 3xx/4xx/5xx é o chamador.
 */
interface HttpClient
{
    /**
     * @throws FalhaTransporteException Quando não há resposta (timeout, conexão, DNS, TLS).
     */
    public function send(HttpRequest $request): HttpResponse;
}
