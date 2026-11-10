# Fixtures

**Todas as páginas desta pasta são sintéticas.** Reproduzem a estrutura HTML da
página `result.asp` da consulta básica da SEFAZ-BA (tabelas, rótulos em `<b>`,
`&nbsp;`, entidades, linhas comentadas, seções repetidas de correspondência e
contador), mas os dados (CNPJ, IE, nomes, endereços) são fictícios e não
correspondem a contribuintes reais.

| Arquivo | Cenário |
| --- | --- |
| `cadastro_completo.html` | Cadastro completo em UTF-8, com acentos crus e em entidades, CNAE sem zero à esquerda, atividade repetida e seções de correspondência/contador com valores diferentes |
| `cadastro_campos_ausentes_latin1.html` | Bytes em ISO-8859-1 com `<meta charset=iso-8859-1>`; nome fantasia, complemento, natureza jurídica, forma de pagamento e atividades secundárias ausentes |
| `cadastro_sem_cnpj.html` | Registro identificado por CPF, sem campo CNPJ |
| `pagina_inesperada.html` | Página HTML que não é o resultado da consulta (aviso de manutenção) |
