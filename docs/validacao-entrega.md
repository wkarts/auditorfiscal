# Validação da entrega

## Catálogo portado para o banco de dados

O seed inicial não depende das planilhas XLSX em tempo de execução.
Os dados foram convertidos para JSON Lines comprimido e são carregados por `Database\\Seeders\\FiscalCatalogSeeder`.

Contagens verificadas pelo manifesto:

- 15.638 registros de parametrização NCM × CST × cClassTrib;
- 10.513 NCMs completos distintos;
- 19 registros oficiais de CST IBS/CBS;
- 142 registros oficiais de cClassTrib.

O módulo administrativo permite:

- criar revisão editável de uma versão publicada;
- incluir, editar e excluir parametrizações manualmente;
- importar uma nova planilha XLSX;
- validar CST, cClassTrib, redução, formato, vigência e conflitos;
- consultar erros de importação;
- publicar uma versão validada;
- comparar versões;
- manter auditorias antigas vinculadas ao snapshot original.

## Conjunto documental de referência

O script `scripts/validate-reference-dataset.py` foi executado contra o ZIP fornecido para o projeto.

Resultado:

| Indicador | Resultado |
|---|---:|
| XMLs processados | 34 |
| Itens | 34 |
| Entradas | 11 |
| Saídas | 23 |
| Valor total | R$ 2.797.000,00 |
| Base IBS/CBS das saídas | R$ 1.888.209,48 |
| IBS das saídas | R$ 1.888,19 |
| CBS das saídas | R$ 16.993,84 |
| Possíveis duplicidades | 2 pares |
| Aquisições de bem usado em classificação genérica | 11 |
| NCM incompatível com veículo | 1 |
| Divergência de arredondamento PIS/COFINS | 1 |
| Divergência de margem | 1 |

A validação da margem usa a base PIS/COFINS explicitamente informada no XML. No caso de referência, o motor registra R$ 4.850,00 contra margem documental de R$ 3.000,00, diferença de R$ 1.850,00.

## Testes executados no ambiente de geração

- testes Python do motor fiscal: aprovados;
- parsing seguro de XML e XLSX: aprovado;
- regra UB16-10: aprovada no XML sintético;
- geração de PDF e Excel: aprovada;
- regra cruzada de margem: aprovada;
- validação integral do ZIP de referência: aprovada;
- sintaxe PHP de todos os arquivos da API: aprovada.

O build do frontend está configurado no GitHub Actions e no Dockerfile. O ambiente de geração não conseguiu baixar dependências NPM por restrição do registry interno, portanto o bundle frontend deve ser confirmado pelo workflow `CI` após a publicação do repositório.
