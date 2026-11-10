<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal respondeu normalmente, mas não há cadastro para o CNPJ consultado.
 */
final class CadastroNaoEncontradoException extends ConsultaException
{
}
