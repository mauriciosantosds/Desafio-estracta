<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use Closure;
use InvalidArgumentException;
use ProvaSpider\Exception\FalhaTransporteException;

/**
 * Repete a requisição apenas em falhas recuperáveis: falha de transporte ou status 5xx.
 * Respostas 2xx, 3xx e 4xx são devolvidas na primeira tentativa.
 */
final class RetryingHttpClient implements HttpClient
{
    /** @var Closure(int): void */
    private readonly Closure $sleep;

    /**
     * @param int $maxTentativas Total de tentativas, incluindo a primeira.
     * @param int $backoffMs Espera antes da 2ª tentativa; dobra a cada nova tentativa.
     * @param (callable(int): void)|null $sleep Recebe milissegundos; substituível nos testes.
     */
    public function __construct(
        private readonly HttpClient $inner,
        private readonly int $maxTentativas = 3,
        private readonly int $backoffMs = 500,
        ?callable $sleep = null,
    ) {
        if ($maxTentativas < 1) {
            throw new InvalidArgumentException('maxTentativas deve ser pelo menos 1.');
        }

        $this->sleep = $sleep !== null
            ? Closure::fromCallable($sleep)
            : static function (int $ms): void {
                usleep($ms * 1000);
            };
    }

    public function send(HttpRequest $request): HttpResponse
    {
        for ($tentativa = 1; ; $tentativa++) {
            try {
                $response = $this->inner->send($request);

                if ($response->status < 500 || $tentativa >= $this->maxTentativas) {
                    return $response;
                }
            } catch (FalhaTransporteException $e) {
                if ($tentativa >= $this->maxTentativas) {
                    throw $e;
                }
            }

            ($this->sleep)($this->backoffMs * 2 ** ($tentativa - 1));
        }
    }
}
