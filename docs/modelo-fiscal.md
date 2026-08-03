# Modelo fiscal e regras

## Base IBS/CBS — UB16-10

```text
BC = vProd + vServ + vFrete + vSeg + vOutro + vII
     - vDesc - vPIS - vCOFINS - vICMS - vICMSUFDest
     - vFCP - vFCPUFDest - vICMSMono - vISSQN + vIS
```

O cálculo é executado por item com `Decimal` e `ROUND_HALF_UP`. A soma de valores já arredondados por item é preservada para evitar divergências de centavos.

## NCM × ClassTrib
A chave de seleção é NCM completo, EX quando existente, vigência e condições. A resolução não remove pontuação de códigos hierárquicos para transformá-los automaticamente em NCM completo. A ordem é: regra específica com EX, regra exata sem EX, ausência. Herança de pai somente quando `allow_child_inheritance=true`.

Estados: `MATCH_EXACT`, `NCM_NOT_PARAMETERIZED`, `PARAMETER_INVALID`, `AMBIGUOUS_PARAMETERIZATION`, `DOCUMENT_CLASSIFICATION_MISSING`, `DOCUMENT_CST_DIVERGENT`, `DOCUMENT_CCLASS_DIVERGENT` e `DOCUMENT_CST_CCLASS_DIVERGENT`.

## Achados implementados
- Reconstrução da base e recálculo de IBS/CBS.
- Arredondamento de PIS/COFINS.
- Validação NCM, EX, CST e cClassTrib.
- Qualidade da própria parametrização.
- Aquisição de bem móvel usado em classificação genérica.
- NCM incompatível com item identificado como veículo, somente quando houver evidência específica de veículo ou bem móvel usado.
- Possível duplicidade por identificador individual (chassi, IMEI, série ou código de agregação), contraparte, valor e tempo.
- Conciliação entrada × saída genérica por produto, priorizando identificador individual, lote e GTIN.
- Correspondência por NCM + descrição + unidade classificada como indicativa e incapaz de sustentar sozinha conclusão de margem.
- Eventos de cancelamento quando enviados no lote.

## Identidade e conciliação de produtos

A auditoria não presume que a empresa pertence ao segmento automotivo. A identidade econômica segue uma hierarquia explícita:

1. chassi, IMEI, número de série ou código de agregação: correspondência individual exata;
2. lote fiscal (`rastro/nLote`): correspondência forte dentro do produto informado;
3. GTIN (`cEAN` ou `cEANTrib`): correspondência forte por produto padronizado;
4. NCM + descrição normalizada + unidade: correspondência apenas indicativa.

Para mercadorias fungíveis, o custo é estimado pela média documental das entradas encontradas e aplicado à quantidade de saída. A margem é exibida somente quando as quantidades são válidas e a amostra contém entradas suficientes. Estoque inicial, entradas fora do período e códigos de fornecedores diferentes permanecem como limitações explícitas. Regras de veículos usados são executadas somente para itens identificados como tal.

Todo achado contém regra, versão, severidade, evidência, impacto e ação recomendada.
