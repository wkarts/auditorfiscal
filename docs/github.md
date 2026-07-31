# Governança GitHub
Proteja `main` e `develop`: proíba push direto, exija PR, uma aprovação, resolução de conversas e os checks `api`, `engine`, `web`, `compose`, `scan` e `secrets`. Use Conventional Commits. O Release Please abre PR de release, atualiza versão/changelog e cria tag; a tag publica imagens GHCR e artefatos.

Branches: `feature/*`, `fix/*`, `release/*`, `hotfix/*`. Substitua `@OWNER` em `.github/CODEOWNERS` pelo usuário ou equipe responsável.
