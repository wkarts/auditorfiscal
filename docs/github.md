# Governança GitHub

Proteja `main` e `develop`: proíba push direto, exija Pull Request, pelo menos uma aprovação, resolução das conversas e todos os checks obrigatórios. Use Conventional Commits. O Release Please abre o PR de release, atualiza versão e changelog e cria a tag.

## Branches

- `feature/*`
- `fix/*`
- `release/*`
- `hotfix/*`

Substitua `@OWNER` em `.github/CODEOWNERS` pelo usuário ou equipe responsável.

## Imagens Docker no GitHub Container Registry

O workflow `Container Images` valida as três imagens em Pull Requests e publica no GHCR em pushes para `main`, tags SemVer e execução manual.

Imagens publicadas:

```text
ghcr.io/wkarts/auditorfiscal-api
ghcr.io/wkarts/auditorfiscal-web
ghcr.io/wkarts/auditorfiscal-fiscal-engine
```

Tags geradas:

- `edge`, `main` e `latest` para o branch principal;
- `sha-<commit>` para rastreabilidade;
- `1.0.0`, `1.0` e `latest` para uma tag estável `v1.0.0`;
- `pr-<número>` apenas para validação, sem publicação.

O workflow utiliza `GITHUB_TOKEN` com `packages: write`, gera SBOM, proveniência e atestação da imagem. Os labels OCI ligam cada pacote ao repositório de origem.

Após a primeira publicação, abra **Packages**, selecione cada imagem e defina a visibilidade desejada. Pacotes públicos podem ser baixados anonimamente. Para pacotes privados, autentique a VPS com um PAT clássico contendo `read:packages`:

```bash
echo "$GHCR_TOKEN" | docker login ghcr.io -u wkarts --password-stdin
```

Nunca salve o PAT no repositório.
