<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\EntradaInvalidaException;
use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\ValueObject\Consulta;

/*
 * Testes contra o portal real. Ficam fora de `composer test` e rodam com:
 *
 *   SEFAZ_BA_CNPJ=<cnpj cadastrado na BA> composer test:integration
 *
 * Sem SEFAZ_BA_CNPJ, o teste de cadastro existente é ignorado.
 */

it('consulta um cadastro existente no portal', function () {
    $cnpj = getenv('SEFAZ_BA_CNPJ');
    if ($cnpj === false || $cnpj === '') {
        $this->markTestSkipped('Defina SEFAZ_BA_CNPJ com um CNPJ cadastrado na SEFAZ-BA.');
    }

    $consulta = new Consulta($cnpj);
    $cadastro = (new SefazBaSpider())->consultar($consulta);

    expect($cadastro->cnpj)->toBe($consulta->cnpj)
        ->and($cadastro->razaoSocial)->not->toBeNull()
        ->and($cadastro->inscricaoEstadual)->toMatch('/^\d+$/');
})->group('integration');

it('informa ausência de cadastro para CNPJ válido não inscrito na BA', function () {
    // CNPJ de exemplo com dígitos verificadores válidos, sem inscrição na BA.
    expect(fn () => (new SefazBaSpider())->consultar(new Consulta('11444777000161')))
        ->toThrow(CadastroNaoEncontradoException::class);
})->group('integration');

it('informa CNPJ incorreto quando o dígito verificador não confere', function () {
    expect(fn () => (new SefazBaSpider())->consultar(new Consulta('11444777000162')))
        ->toThrow(EntradaInvalidaException::class);
})->group('integration');
