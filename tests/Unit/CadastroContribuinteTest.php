<?php

declare(strict_types=1);

use ProvaSpider\ValueObject\AtividadeEconomica;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Endereco;

it('serializa o objeto de valor preservando identificadores e campos ausentes', function () {
    $cadastro = new CadastroContribuinte(
        inscricaoEstadual: '000000000',
        cnpj: '00000000000000',
        razaoSocial: 'EMPRESA FICTÍCIA DE TESTE',
        nomeFantasia: null,
        situacaoCadastral: 'VALOR FICTÍCIO',
        endereco: null,
        atividadeEconomica: null,
        regimeApuracao: null,
    );

    $resultado = json_decode(json_encode($cadastro, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    expect($resultado)
        ->toHaveKey('nomeFantasia', null)
        ->and($resultado['cnpj'])->toBe('00000000000000')
        ->and($resultado['inscricaoEstadual'])->toBe('000000000');
});

it('serializa endereço e atividades como objetos aninhados', function () {
    $cadastro = new CadastroContribuinte(
        inscricaoEstadual: '001234567',
        cnpj: '00123456000191',
        razaoSocial: 'EMPRESA FICTÍCIA DE TESTE',
        nomeFantasia: null,
        situacaoCadastral: 'ATIVO',
        endereco: new Endereco('RUA FICTÍCIA', '10', null, 'CENTRO', '01000000', 'SALVADOR', 'BA'),
        atividadeEconomica: new AtividadeEconomica('0111302', 'Cultivo de milho'),
        regimeApuracao: 'C/CORRENTE FISCAL',
        naturezaJuridica: null,
        condicao: 'NORMAL',
        atividadesSecundarias: [new AtividadeEconomica('4120400', 'Construção de edifícios')],
    );

    $json = json_decode(json_encode($cadastro, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    expect($json['endereco'])->toBe([
        'logradouro' => 'RUA FICTÍCIA',
        'numero' => '10',
        'complemento' => null,
        'bairro' => 'CENTRO',
        'cep' => '01000000',
        'municipio' => 'SALVADOR',
        'uf' => 'BA',
    ])
        ->and($json['atividadeEconomica'])->toBe(['codigo' => '0111302', 'descricao' => 'Cultivo de milho'])
        ->and($json['atividadesSecundarias'])->toBe([['codigo' => '4120400', 'descricao' => 'Construção de edifícios']])
        ->and($json['naturezaJuridica'])->toBeNull();
});
