<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use ProvaSpider\Exception\FalhaTransporteException;

final class GuzzleHttpClient implements HttpClient
{
    private readonly ClientInterface $client;

    public function __construct(
        ?ClientInterface $client = null,
        private readonly float $timeout = 20.0,
        private readonly float $connectTimeout = 10.0,
    ) {
        $this->client = $client ?? new Client();
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $options = [
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
            // Redirecionamentos são tratados (e limitados) pelo spider.
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => $request->headers,
        ];

        if ($request->form !== []) {
            $options[RequestOptions::FORM_PARAMS] = $request->form;
        }

        try {
            $response = $this->client->request($request->method, $request->url, $options);
        } catch (GuzzleException $e) {
            throw new FalhaTransporteException(
                sprintf('Falha de comunicação com %s: %s', $request->url, $e->getMessage()),
                0,
                $e,
            );
        }

        $headers = [];
        foreach ($response->getHeaders() as $nome => $valores) {
            $headers[$nome] = implode(', ', $valores);
        }

        return new HttpResponse($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
