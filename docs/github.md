# GitHub, branches, proteção e distribuição

## Estrutura de branches

- `main`: código aprovado, publicável e protegido;
- `develop`: integração opcional para mudanças longas;
- `feature/*`: funcionalidades;
- `fix/*`: correções;
- `release/*`: preparação de versão;
- `hotfix/*`: correções urgentes sobre produção.

## Proteção obrigatória de `main`

Em **Settings → Branches/Rulesets**, bloqueie push direto e exija Pull Request.
Marque como obrigatórios:

- `CI / Repository contracts`;
- `CI / API (Laravel)`;
- `CI / Fiscal engine (Python)`;
- `CI / Web (Vue/TypeScript)`;
- `CI / Docker Compose validation`;
- `Container Validation / Build, integrate and scan images (linux/amd64)`;
- os três builds ARM64;
- `Security / Trivy filesystem scan`;
- `Security / Gitleaks repository scan`.

Exija branch atualizada antes do merge, resolução das conversas e ao menos uma
aprovação quando houver mais de um mantenedor.

## Permissões do GitHub Actions

Em **Settings → Actions → General**:

1. habilite GitHub Actions;
2. em **Workflow permissions**, permita leitura e escrita;
3. permita que Actions criem e aprovem Pull Requests somente se essa automação
   for realmente utilizada;
4. mantenha actions de terceiros restritas às organizações aprovadas ou fixe-as
   por SHA em ambientes de maior criticidade.

O workflow de release declara explicitamente `contents: write`, `packages:
write`, `attestations: write` e `id-token: write`.

## Fluxo de versão

Prepare a versão na branch:

```bash
./scripts/release.sh 1.0.2
git push -u origin release/v1.0.2
```

Abra o Pull Request para `main`. A PR constrói e executa as imagens reais sem
publicá-las. Depois do merge, a release:

1. repete testes de qualidade;
2. compila imagens AMD64/ARM64;
3. publica no GHCR;
4. baixa as imagens publicadas em uma stack limpa;
5. executa migration, seed, healthcheck e login;
6. cria tag e GitHub Release somente após a validação.

## Imagens

O namespace é obtido de `github.repository_owner`, sem usuário fixo no código:

```text
ghcr.io/<owner>/auditorfiscal-api:<versão>
ghcr.io/<owner>/auditorfiscal-web:<versão>
ghcr.io/<owner>/auditorfiscal-fiscal-engine:<versão>
```

## CODEOWNERS

O projeto fornece `.github/CODEOWNERS.example`. Copie para
`.github/CODEOWNERS` somente depois de substituir os exemplos por usuários ou
equipes que realmente existam. Um identificador fictício em `CODEOWNERS` gera
avisos e não protege o repositório.

## Histórico sanitizado

Quando o repositório anterior continha dados de clientes, não basta removê-los do
último commit. Use o bundle de histórico limpo e siga
`docs/deploy/history-sanitization.md`.
