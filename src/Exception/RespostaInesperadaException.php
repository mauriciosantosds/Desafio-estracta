<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal respondeu, mas em um formato que o spider não reconhece.
 */
final class RespostaInesperadaException extends ConsultaException
{
}
