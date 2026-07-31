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
- NCM incompatível com item identificado como veículo.
- Possível duplicidade por chassi, contraparte, valor e tempo.
- Conciliação entrada × saída por chassi e coerência da margem.
- Eventos de cancelamento quando enviados no lote.

Todo achado contém regra, versão, severidade, evidência, impacto e ação recomendada.
