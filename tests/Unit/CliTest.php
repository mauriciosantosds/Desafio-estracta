<?php

declare(strict_types=1);

/**
 * Executa bin/consultar.php em um processo separado. Só cobre casos que não acessam a rede.
 *
 * @return array{codigo: int, stdout: string, stderr: string}
 */
function executarCli(string ...$args): array
{
    $comando = array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/consultar.php'], $args);
    $processo = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['codigo' => proc_close($processo), 'stdout' => $stdout, 'stderr' => $stderr];
}

it('escreve o uso em stderr e sai com código 1 sem argumento', function () {
    $resultado = executarCli();

    expect($resultado['codigo'])->toBe(1)
        ->and($resultado['stdout'])->toBe('')
        ->and($resultado['stderr'])->toContain('Uso:');
});

it('rejeita CNPJ com formato inválido antes de consultar o portal', function () {
    $resultado = executarCli('123');

    expect($resultado['codigo'])->toBe(1)
        ->and($resultado['stdout'])->toBe('')
        ->and($resultado['stderr'])->toContain('Entrada inválida: O CNPJ deve ter 14 dígitos.');
});
