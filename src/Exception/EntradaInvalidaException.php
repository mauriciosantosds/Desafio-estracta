<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal rejeitou o CNPJ informado (por exemplo, dígito verificador incorreto).
 */
final class EntradaInvalidaException extends ConsultaException
{
}
