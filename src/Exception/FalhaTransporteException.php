<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * Não foi possível obter resposta do portal: timeout, falha de conexão ou DNS (indisponibilidade).
 */
final class FalhaTransporteException extends ConsultaException
{
}
