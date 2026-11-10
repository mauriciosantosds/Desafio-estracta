# Como desenvolvi

## Abordagem

Comecei pelo portal: antes de escrever qualquer classe, quis entender exatamente o que o formulário envia e o que volta em cada cenário. Com isso em mãos, dividi o trabalho em camadas pequenas que dava para testar isoladamente (transporte, extração, mapeamento e o spider amarrando tudo) e fui entregando uma branch por etapa.

PHP não estava instalado na minha máquina, então rodei tudo com Docker (`composer:2` para instalar dependências e `php:8.1-cli` para os testes e o comando). Testar em 8.1 foi de propósito, porque é a versão mínima do enunciado.

## Investigando o portal

Usei `curl` no terminal, guardando headers e corpo de cada resposta:

- `consultaBa.asp` tem um único `<form action="result.asp" method="post">` com os campos `sefp=1`, `estado=BA`, `CGC`, `CPF`, `IE` e um botão de submit por tipo de consulta (`B1` para CNPJ). Não tem captcha nem token escondido.
- A página inicial cria um cookie `ASPSESSIONID`, mas testei o POST direto, sem cookie, e o resultado foi o mesmo. Não existe etapa intermediária, então a consulta é uma única requisição. Mesmo assim envio o `Referer` da página do formulário, como um navegador faria.
- O CNPJ precisa ir só com dígitos. Com máscara, o portal redireciona para `param_errado.htm`.
- Os casos de erro vêm como `302`: `consulta_vazia.htm` quando não há cadastro e `cnpj_incorreto.htm` quando o dígito verificador não confere. Esse foi o achado mais importante: **as duas páginas de destino respondem 404**. Se eu deixasse o cliente HTTP seguir redirecionamentos sozinho, "CNPJ sem cadastro" viraria "erro HTTP 404", que é justamente a confusão que o enunciado pede para evitar.
- Codificação: a página do formulário é ISO-8859-1, mas a de resultado declara UTF-8 no `<meta>` e de fato está em UTF-8. Ela ainda mistura entidades (`&ccedil;`, `&nbsp;`) com acentos crus, e há trechos comentados com texto duplamente codificado (`Ã§`).
- O HTML é uma sequência de tabelas com células no formato `<td><b>Rótulo:</b>&nbsp;valor</td>`. Os rótulos se repetem: `Número`, `CEP`, `Município` e `Complemento` aparecem de novo nas seções "Endereço de Correspondência" e "Informações do Contador". Também há linhas inteiras comentadas com `<!-- -->`, com valores antigos.

## Decisões técnicas

**Transporte separado da extração.** O spider depende de uma interface `HttpClient` minha, e não do Guzzle. A implementação com Guzzle só traduz a requisição, aplica timeout (20 s no total, 10 s para conectar) e transforma exceções sem resposta em `FalhaTransporteException`. Ela não segue redirecionamentos e não lança exceção por status, porque quem sabe o que um 302 ou um 404 significa neste portal é o spider. Isso também deixou os testes simples: o `FakeHttpClient` devolve respostas enfileiradas e registra o que recebeu, então consigo verificar a requisição montada sem mock de biblioteca.

**Redirecionamentos tratados no spider.** Antes de seguir qualquer `Location`, o spider confere o nome da página de destino. `consulta_vazia.htm` vira `CadastroNaoEncontradoException`; `cnpj_incorreto.htm` e `param_errado.htm` viram `EntradaInvalidaException`. Qualquer outro destino é seguido com GET, até 3 saltos; passou disso, é `RespostaInesperadaException`. Assim o limite de redirecionamentos fica explícito e testado.

**Retry como decorator.** `RetryingHttpClient` Ele repete só falha de transporte e 5xx, no máximo 3 tentativas, com backoff exponencial (500 ms e depois 1 s). 4xx e 3xx nunca são repetidos, porque repetir não muda o resultado. A função de espera é injetada, então os testes não dormem.

**Exceções.** Todas estendem a `ConsultaException` do projeto-base, então quem só quer saber "deu certo ou não" captura uma só, e quem precisa diferenciar tem uma classe por caso: entrada inválida, cadastro não encontrado, falha HTTP (com o `status`), falha de transporte e resposta inesperada. Mantive a validação estrutural do `Consulta` com `InvalidArgumentException`, como no projeto-base e nos testes que já existiam.

**Encoding.** O transporte devolve o corpo em bytes, sem converter nada. O `HtmlDecoder` decide o charset pelo header `Content-Type`, depois pelo `<meta>`, e se o declarado for UTF-8 mas os bytes não forem UTF-8 válido, assume Windows-1252 (que cobre o ISO-8859-1 e os caracteres que sites ASP costumam usar). Antes de passar para o `DOMDocument`, converto todo caractere não-ASCII em entidade numérica. Assim o libxml não depende do `<meta charset>` da página, que nem sempre condiz com os bytes.

**Extração por seção.** O `PaginaCadastroParser` percorre as células (`//th | //td`) em ordem de documento e usa os cabeçalhos ("Identificação", "Endereço", "Informações Complementares") para saber em que seção está. Ele para ao chegar em "Endereço de Correspondência" ou "Informações do Contador", o que resolve os rótulos repetidos sem depender de posição fixa de tabela. As linhas comentadas são ignoradas naturalmente, porque viram nós de comentário no DOM. As atividades econômicas são linhas sem rótulo logo abaixo de "Atividade Econômica Principal/Secundária", e o parser as junta numa lista. Se a página não tem a seção "Dados da empresa", não é o resultado esperado e vira `RespostaInesperadaException`.

**Mapeamento separado do parser.** O parser só devolve texto normalizado; as regras de negócio ficam no `CadastroMapper`. Isso me deixou testar as transformações com uma `PaginaCadastro` montada à mão, sem HTML.

**Ajustes no esquema.** O portal publica o endereço em partes e a atividade com código e descrição, então `endereco` virou um objeto `Endereco` e `atividadeEconomica` um `AtividadeEconomica`. Acrescentei `naturezaJuridica`, `condicao` e `atividadesSecundarias` como parâmetros opcionais no fim do construtor, para não quebrar quem já usava o contrato. Para `regimeApuracao` usei o campo "Forma de pagamento" (`C/CORRENTE FISCAL`, `SIMPLES NACIONAL`), porque conta corrente fiscal é o regime normal de apuração do ICMS; "Condição" (`NORMAL`, `EMPRESA PEQUENO PORTE`) ficou em `condicao`. Foi uma decisão de interpretação e está documentada no código.

**Identificadores.** CNPJ, IE e CEP saem sem máscara e como string. A IE vem com um sufixo (`001.027.389 NO`, `023.757.934 PP`) cujo significado a página não explica, então guardo só o número. Campos vazios na página (`Nome Fantasia:&nbsp;`) e campos que nem aparecem viram `null`.

**CNPJ do resultado.** Se a página publica um CNPJ diferente do consultado, lanço `RespostaInesperadaException`, porque prefiro falhar a devolver o cadastro de outra empresa. Se a página não publica CNPJ nenhum, o campo fica `null` e o CNPJ da entrada não é copiado para lá.

## Dificuldades

- O 302 para páginas 404 foi a maior pegadinha. Só percebi porque, ao investigar, fiz `curl` nas páginas de destino para ver a mensagem que elas mostravam.
- Duas páginas do mesmo fluxo com codificações diferentes, e uma delas com entidades, UTF-8 e texto duplamente codificado ao mesmo tempo. O fallback por validade de UTF-8 e a conversão para entidades antes do `loadHTML` resolveram sem precisar de caso especial.
- O CNPJ `00000000000000` (estruturalmente válido, inclusive nos dígitos verificadores) não cai em "sem cadastro": o portal devolve um registro identificado por **CPF**, sem campo CNPJ. Foi esse caso que me fez tratar CNPJ ausente e CNPJ divergente de formas diferentes, e criar uma fixture só para ele.
- O portal às vezes publica o código CNAE sem o zero à esquerda (`111302` em vez de `0111302`) e repete atividades secundárias. Completo o código até 7 dígitos, porque subclasse CNAE sempre tem 7, e removo as repetições.
- Para as fixtures, não quis usar páginas reais nem anonimizar uma página inteira. Escrevi HTMLs sintéticos que reproduzem a estrutura que importa (rótulos, `&nbsp;`, comentários, seções repetidas, uma página gravada em ISO-8859-1) com dados inventados. Validei o parser contra as páginas reais só localmente, sem versioná-las.
- O `composer audit` acusa um alerta no PHPUnit 10.5.36. O Pest 2.36 fixa exatamente essa versão, então não dá para atualizar sem trocar de major do Pest. Como é dependência de desenvolvimento e o problema é na cobertura de testes PHPT, que não uso, deixei registrado no README.
- A imagem `composer:2` vem com PHP 8.5. Para o `composer.lock` não puxar pacotes que exigem PHP mais novo que o mínimo, fixei `config.platform.php` em 8.1.

## O que aprendi

- Em scraping, as respostas de erro dizem tanto quanto as de sucesso, e vale investigar cada uma delas (inclusive o destino dos redirecionamentos) antes de decidir a arquitetura.
- Entendi melhor como o libxml do PHP lida com charset: ele respeita o `<meta>` do documento, e por isso vale garantir a entrada já em ASCII com entidades.
- Uma interface de transporte minha, em vez da da biblioteca, deixou os testes do spider bem mais legíveis do que usar o `MockHandler` do Guzzle em todo lugar. O `MockHandler` ficou só nos testes do próprio adaptador.

## O que faria com mais tempo

- Validar os dígitos verificadores localmente, para não gastar uma requisição com um CNPJ que o portal vai rejeitar de qualquer jeito.
- Extrair também o motivo da situação (a página mostra quando o contribuinte está inapto), a data de inclusão e a forma de atuação.
- Rodar PHPStan e PHP-CS-Fixer num pipeline de CI (GitHub Actions) junto com a suíte, e a suíte de integração num job agendado, para perceber cedo se o layout do portal mudar.
- Avaliar subir para PHP 8.2 e Pest 3, o que resolveria o alerta do PHPUnit.
- Adicionar logs das requisições (tempo, status, tentativas) para facilitar diagnóstico em produção.
