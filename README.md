# Desafio estracta

Spider em PHP que consulta o cadastro de contribuintes do ICMS da Bahia (SEFAZ-BA) **por CNPJ** e devolve um objeto de valor imutável `CadastroContribuinte`. O relato de como desenvolvi está em [EXPLICACAO.md](EXPLICACAO.md).

## Instalação

Requisitos: PHP 8.1+ com as extensões `dom`, `xml`, `mbstring` e `curl` (usada pelo Guzzle), e Composer 2.

```bash
composer install
```

O `composer.json` fixa `config.platform.php` em 8.1, então o `composer.lock` resolve dependências compatíveis com PHP 8.1 mesmo quando gerado numa versão mais nova.

Sem PHP instalado, dá para usar Docker Compose. A imagem (ver [Dockerfile](Dockerfile)) é a `php:8.1-cli`, versão mínima do projeto, com Composer 2 e `unzip`:

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app composer test
docker compose run --rm app php bin/consultar.php 00.000.000/0000-00
SEFAZ_BA_CNPJ=<cnpj> docker compose run --rm app composer test:integration
```

O container roda com o UID/GID do host (padrão `1000:1000`), para que `vendor/` não fique com dono root. Se os seus forem diferentes, exporte antes: `export UID GID=$(id -g)`.

## Testes

```bash
composer test                # suíte padrão, sem acesso à internet
composer test:integration    # opcional: consulta o portal real
SEFAZ_BA_CNPJ=<cnpj> composer test:integration   # inclui o cenário de cadastro existente
```

A suíte padrão usa respostas locais: as fixtures em [tests/Fixtures](tests/Fixtures) são **sintéticas** (estrutura do portal, dados fictícios) e o transporte HTTP é substituído por `FakeHttpClient`.

## Uso

```bash
php bin/consultar.php 00.000.000/0000-00
```

O CNPJ pode vir com ou sem máscara. Em caso de sucesso, o JSON vai para `stdout`; em caso de erro, a mensagem vai para `stderr` e o código de saída é 1. Exemplo de saída (dados fictícios):

```json
{
    "inscricaoEstadual": "001234567",
    "cnpj": "00123456000191",
    "razaoSocial": "COMÉRCIO FICTÍCIO DE AÇAÍ LTDA",
    "nomeFantasia": null,
    "situacaoCadastral": "ATIVO",
    "endereco": {
        "logradouro": "RUA DAS FLORES FICTÍCIAS",
        "numero": "0012",
        "complemento": null,
        "bairro": "Jardim Imaginário",
        "cep": "01234567",
        "municipio": "SÃO FICTÍCIO",
        "uf": "BA"
    },
    "atividadeEconomica": { "codigo": "0111302", "descricao": "Cultivo de milho" },
    "regimeApuracao": "C/CORRENTE FISCAL",
    "naturezaJuridica": "SOCIEDADE EMPRESARIA LIMITADA",
    "condicao": "NORMAL",
    "atividadesSecundarias": [
        { "codigo": "4120400", "descricao": "Construção de edifícios" }
    ]
}
```

## Como funciona

| Camada | Classe | Responsabilidade |
| --- | --- | --- |
| Transporte | `Http\HttpClient`, `GuzzleHttpClient` | Envia a requisição com timeout (20 s total, 10 s de conexão), sem seguir redirecionamentos e sem lançar exceção por status |
| Retry | `Http\RetryingHttpClient` | Até 3 tentativas, só para falha de transporte e 5xx, com backoff exponencial (500 ms, 1 s) |
| Consulta | `Spider\SefazBaSpider` | Monta o POST para `result.asp`, interpreta redirecionamentos (até 3 saltos) e status, confere o CNPJ devolvido |
| Charset | `Extraction\HtmlDecoder` | Converte para UTF-8 (header → `<meta>` → fallback Windows-1252) |
| Extração | `Extraction\PaginaCadastroParser` | DOMXPath: pares rótulo/valor agrupados por seção |
| Mapeamento | `Extraction\CadastroMapper` | Máscaras, `null` para ausentes, CNAE com 7 dígitos |

### Campos

| Propriedade | Origem na página | Transformação |
| --- | --- | --- |
| `inscricaoEstadual` | Inscrição Estadual | Só dígitos; o sufixo publicado (ex.: `NO`) é descartado |
| `cnpj` | CNPJ | Só dígitos; `null` se a página não publicar CNPJ |
| `razaoSocial`, `nomeFantasia`, `naturezaJuridica` | Dados da empresa | Espaços normalizados |
| `situacaoCadastral` | Situação Cadastral Vigente | — |
| `endereco` | Seção Endereço (não a de correspondência) | CEP só com dígitos; `null` se a seção não existir |
| `atividadeEconomica` | Atividade Econômica Principal | `{codigo, descricao}`, código com 7 dígitos |
| `atividadesSecundarias` | Atividade Econômica Secundária | Lista, sem repetições |
| `regimeApuracao` | Forma de pagamento | — |
| `condicao` | Condição | — |

### Erros

Todas as exceções da consulta estendem `ConsultaException`.

| Situação | Exceção |
| --- | --- |
| CNPJ com formato inválido (validação local) | `InvalidArgumentException` (em `Consulta`) |
| Portal redireciona para `cnpj_incorreto.htm` ou `param_errado.htm` | `EntradaInvalidaException` |
| Portal redireciona para `consulta_vazia.htm` | `CadastroNaoEncontradoException` |
| Status HTTP ≥ 400 | `FalhaHttpException` (com `status`) |
| Timeout, conexão, DNS, TLS | `FalhaTransporteException` |
| HTML sem a estrutura do cadastro, excesso de redirecionamentos, CNPJ devolvido diferente do consultado | `RespostaInesperadaException` |

### Observação sobre dependências

`composer audit` aponta um alerta no `phpunit/phpunit` 10.5.36 (CVE-2026-24765, cobertura de testes PHPT). Ele é dependência de desenvolvimento, fixada exatamente nessa versão pelo Pest 2.36, que é a versão pedida no enunciado; a suíte não usa PHPT nem coleta de cobertura.

