<?php

declare(strict_types=1);

use ProvaSpider\Extraction\HtmlDecoder;

it('mantém UTF-8 válido declarado na página', function () {
    $html = '<meta charset="UTF-8"><p>Situação</p>';

    expect(HtmlDecoder::paraUtf8($html))->toBe($html);
});

it('converte ISO-8859-1 declarado no meta', function () {
    $html = fixture('cadastro_campos_ausentes_latin1.html');

    $utf8 = HtmlDecoder::paraUtf8($html, 'text/html');

    expect(mb_check_encoding($utf8, 'UTF-8'))->toBeTrue()
        ->and($utf8)->toContain('JOSÉ DA CONCEIÇÃO FICTÍCIO ME');
});

it('dá preferência ao charset do header Content-Type', function () {
    $latin1 = "<p>Raz\xE3o</p>";

    expect(HtmlDecoder::paraUtf8($latin1, 'text/html; charset=ISO-8859-1'))->toBe('<p>Razão</p>');
});

it('trata como Windows-1252 bytes que não são UTF-8 válido mesmo com UTF-8 declarado', function () {
    $html = "<meta charset=\"utf-8\"><p>Endere\xE7o \x96 n\xBA</p>";

    expect(HtmlDecoder::paraUtf8($html))->toBe('<meta charset="utf-8"><p>Endereço – nº</p>');
});
