<?php

declare(strict_types=1);

use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Extraction\CadastroMapper;
use ProvaSpider\Extraction\HtmlDecoder;
use ProvaSpider\Extraction\PaginaCadastro;
use ProvaSpider\Extraction\PaginaCadastroParser;
use ProvaSpider\ValueObject\AtividadeEconomica;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Endereco;

function cadastroDaFixture(string $nome): CadastroContribuinte
{
    $pagina = (new PaginaCadastroParser())->parse(HtmlDecoder::paraUtf8(fixture($nome)));

    return (new CadastroMapper())->mapear($pagina);
}

it('mapeia o cadastro completo para o objeto de valor', function () {
    $cadastro = cadastroDaFixture('cadastro_completo.html');

    expect($cadastro)->toEqual(new CadastroContribuinte(
        inscricaoEstadual: '001234567',
        cnpj: '00123456000191',
        razaoSocial: 'COMÉRCIO FICTÍCIO DE AÇAÍ LTDA',
        nomeFantasia: 'AÇAÍ DE TESTE',
        situacaoCadastral: 'ATIVO',
        endereco: new Endereco(
            logradouro: 'RUA DAS FLORES FICTÍCIAS',
            numero: '0012',
            complemento: 'SALA 3',
            bairro: 'Jardim Imaginário',
            cep: '01234567',
            municipio: 'SÃO FICTÍCIO',
            uf: 'BA',
        ),
        atividadeEconomica: new AtividadeEconomica('0111302', 'Cultivo de milho'),
        regimeApuracao: 'C/CORRENTE FISCAL',
        naturezaJuridica: 'SOCIEDADE EMPRESARIA LIMITADA',
        condicao: 'NORMAL',
        atividadesSecundarias: [
            new AtividadeEconomica('4120400', 'Construção de edifícios'),
            new AtividadeEconomica('4299599', 'Outras obras de engenharia civil não especificadas anteriormente'),
        ],
    ));
});

it('preserva zeros à esquerda nos identificadores', function () {
    $cadastro = cadastroDaFixture('cadastro_campos_ausentes_latin1.html');

    expect($cadastro->cnpj)->toBe('01000000000100')
        ->and($cadastro->inscricaoEstadual)->toBe('000000010')
        ->and($cadastro->endereco?->cep)->toBe('04000000');
});

it('representa campos ausentes ou vazios como null', function () {
    $cadastro = cadastroDaFixture('cadastro_campos_ausentes_latin1.html');

    expect($cadastro->razaoSocial)->toBe('JOSÉ DA CONCEIÇÃO FICTÍCIO ME')
        ->and($cadastro->nomeFantasia)->toBeNull()
        ->and($cadastro->naturezaJuridica)->toBeNull()
        ->and($cadastro->regimeApuracao)->toBeNull()
        ->and($cadastro->endereco?->complemento)->toBeNull()
        ->and($cadastro->endereco?->logradouro)->toBe('TRAVESSA ÁGUA FRIA')
        ->and($cadastro->atividadesSecundarias)->toBe([])
        ->and($cadastro->condicao)->toBe('MICROEMPRESA')
        ->and($cadastro->situacaoCadastral)->toBe('INAPTO');
});

it('não inventa CNPJ nem endereço quando a página não os publica', function () {
    $cadastro = cadastroDaFixture('cadastro_sem_cnpj.html');

    expect($cadastro->cnpj)->toBeNull()
        ->and($cadastro->endereco)->toBeNull()
        ->and($cadastro->atividadeEconomica)->toBeNull()
        ->and($cadastro->inscricaoEstadual)->toBe('012345678')
        ->and($cadastro->situacaoCadastral)->toBe('BAIXADO');
});

it('rejeita CNPJ publicado com quantidade de dígitos inesperada', function () {
    $pagina = new PaginaCadastro([PaginaCadastro::EMPRESA => ['cnpj' => '12.345']], []);

    expect(fn () => (new CadastroMapper())->mapear($pagina))
        ->toThrow(RespostaInesperadaException::class);
});

it('mantém a descrição quando a atividade não segue o formato código - descrição', function () {
    $pagina = new PaginaCadastro(
        [PaginaCadastro::EMPRESA => []],
        ['atividade econômica principal' => ['ATIVIDADE SEM CÓDIGO']],
    );

    expect((new CadastroMapper())->mapear($pagina)->atividadeEconomica)
        ->toEqual(new AtividadeEconomica(null, 'ATIVIDADE SEM CÓDIGO'));
});
