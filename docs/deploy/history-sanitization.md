# Substituição segura de um histórico Git com dados indevidos

Remover dados pessoais apenas do último commit não os apaga dos commits antigos.
Quando um repositório anterior continha XMLs, relatórios, identificadores de
clientes ou credenciais de demonstração interpretadas como segredo, substitua o
histórico remoto pelo bundle limpo fornecido na release.

## Procedimento recomendado

1. bloqueie temporariamente merges e pushes;
2. faça backup espelhado do repositório antigo em armazenamento restrito;
3. clone o bundle limpo em outro diretório;
4. configure o remoto do GitHub;
5. force a substituição de `main`, da branch de release e das tags autorizadas;
6. feche Pull Requests baseados no histórico anterior;
7. abra um novo Pull Request a partir da branch limpa;
8. revogue links, tokens e artefatos antigos;
9. exclua artifacts e caches antigos do GitHub Actions quando necessário;
10. execute Gitleaks no histórico completo novo.

Exemplo:

```bash
git clone auditor-fiscal-v1.0.1-clean.bundle auditor-fiscal-clean
cd auditor-fiscal-clean
git remote add origin https://github.com/ORGANIZACAO/REPOSITORIO.git

git push --force-with-lease origin main
git push --force origin release/v1.0.1
git push --force origin refs/tags/v1.0.0
git push origin --delete release/v1.0.0 || true
```

A substituição é disruptiva: colaboradores precisam clonar novamente ou fazer
reset explícito para o novo histórico. Não publique o backup histórico em um
local acessível ao público.
