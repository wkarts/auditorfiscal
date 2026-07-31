# Correção do Gitleaks e publicação de imagens no GHCR

## Falha reproduzida

O workflow de segurança encontrou dois falsos positivos históricos da regra `generic-api-key`:

- um placeholder antigo em `.env.example`;
- o mesmo placeholder citado na documentação da validação.

O arquivo de configuração anterior anexava a exceção a uma regra herdada e limitava o caminho apenas ao `.env.example`. Por isso, o registro da documentação continuava falhando e o histórico permanecia bloqueado.

## Correção aplicada

- substituição pela allowlist global introduzida nas versões atuais do Gitleaks;
- limitação por `targetRules = ["generic-api-key"]`;
- condição `AND` entre linha exata e um dos dois caminhos conhecidos;
- inclusão dos dois fingerprints exatos em `.gitleaksignore`;
- passagem explícita de `--gitleaks-ignore-path` no workflow;
- separação entre análise da árvore atual, commits do Pull Request e histórico completo;
- fixação da imagem do Gitleaks por tag e digest OCI.

A exceção não libera outros valores, regras ou arquivos.

## Imagens Docker

O workflow `Container Images` constrói e publica três pacotes no GitHub Container Registry:

```text
ghcr.io/wkarts/auditorfiscal-api
ghcr.io/wkarts/auditorfiscal-web
ghcr.io/wkarts/auditorfiscal-fiscal-engine
```

Os Pull Requests executam apenas o build de validação. Pushes para `main`, tags SemVer e execuções manuais publicam as imagens. A publicação inclui multi-arquitetura, cache BuildKit, SBOM, proveniência, atestação e labels OCI que relacionam o pacote ao repositório.

## Implantação

Em produção, use:

```dotenv
DEPLOY_MODE=ghcr
GHCR_REGISTRY=ghcr.io
GHCR_NAMESPACE=wkarts
AUDITOR_IMAGE_TAG=1.0.0
```

O instalador usa `compose.production.yaml`, baixa as imagens e inicia a stack com `--no-build`. O modo `source` permanece disponível para desenvolvimento local.
