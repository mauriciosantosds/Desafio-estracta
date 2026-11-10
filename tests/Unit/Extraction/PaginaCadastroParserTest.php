<?php

declare(strict_types=1);

use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Extraction\HtmlDecoder;
use ProvaSpider\Extraction\PaginaCadastro;
use ProvaSpider\Extraction\PaginaCadastroParser;

function paginaDaFixture(string $nome): PaginaCadastro
{
    return (new PaginaCadastroParser())->parse(HtmlDecoder::paraUtf8(fixture($nome)));
}

it('lê rótulos e valores normalizando entidades, acentos e espaços', function () {
    $pagina = paginaDaFixture('cadastro_completo.html');

    expect($pagina->campo(PaginaCadastro::EMPRESA, 'CNPJ'))->toBe('00.123.456/0001-91')
        ->and($pagina->campo(PaginaCadastro::EMPRESA, 'Inscrição Estadual'))->toBe('001.234.567 NO')
        ->and($pagina->campo(PaginaCadastro::EMPRESA, 'Razão Social'))->toBe('COMÉRCIO FICTÍCIO DE AÇAÍ LTDA')
        ->and($pagina->campo(PaginaCadastro::EMPRESA, 'Nome Fantasia'))->toBe('AÇAÍ DE TESTE')
        ->and($pagina->campo(PaginaCadastro::ENDERECO, 'Logradouro'))->toBe('RUA DAS FLORES FICTÍCIAS')
        ->and($pagina->campo(PaginaCadastro::ENDERECO, 'Município'))->toBe('SÃO FICTÍCIO');
});

it('ignora linhas comentadas no HTML', function () {
    $pagina = paginaDaFixture('cadastro_completo.html');

    expect($pagina->campo(PaginaCadastro::EMPRESA, 'Razão Social'))->not->toContain('NAO DEVE APARECER')
        ->and($pagina->campo(PaginaCadastro::COMPLEMENTARES, 'Condição'))->toBe('NORMAL');
});

it('não mistura o endereço do estabelecimento com correspondência e contador', function () {
    $pagina = paginaDaFixture('cadastro_completo.html');

    expect($pagina->campo(PaginaCadastro::ENDERECO, 'Número'))->toBe('0012')
        ->and($pagina->campo(PaginaCadastro::ENDERECO, 'Complemento'))->toBe('SALA 3')
        ->and($pagina->campo(PaginaCadastro::ENDERECO, 'CEP'))->toBe('01234-567')
        ->and($pagina->campo(PaginaCadastro::COMPLEMENTARES, 'Situação Cadastral Vigente'))->toBe('ATIVO');
});

it('agrupa as linhas de atividades sob seus rótulos', function () {
    $pagina = paginaDaFixture('cadastro_completo.html');

    expect($pagina->lista('Atividade Econômica Principal'))->toBe(['111302 - Cultivo de milho'])
        ->and($pagina->lista('Atividade Econômica Secundária'))->toBe([
            '4120400 - Construção de edifícios',
            '4299599 - Outras obras de engenharia civil não especificadas anteriormente',
            '4299599 - Outras obras de engenharia civil não especificadas anteriormente',
        ]);
});

it('registra campo vazio como null e diferencia de campo inexistente', function () {
    $pagina = paginaDaFixture('cadastro_campos_ausentes_latin1.html');

    expect($pagina->temCampo(PaginaCadastro::EMPRESA, 'Nome Fantasia'))->toBeTrue()
        ->and($pagina->campo(PaginaCadastro::EMPRESA, 'Nome Fantasia'))->toBeNull()
        ->and($pagina->temCampo(PaginaCadastro::COMPLEMENTARES, 'Forma de pagamento'))->toBeFalse()
        ->and($pagina->lista('Atividade Econômica Secundária'))->toBe([]);
});

it('rejeita HTML que não é a página de cadastro', function (string $html) {
    expect(fn () => (new PaginaCadastroParser())->parse($html))
        ->toThrow(RespostaInesperadaException::class);
})->with([
    'página de manutenção' => [fixture('pagina_inesperada.html')],
    'corpo vazio' => [''],
    'texto qualquer' => ['erro interno'],
]);
