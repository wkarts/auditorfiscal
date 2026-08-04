# Validação Trivy das imagens

A Pull Request constrói e executa as imagens `linux/amd64` antes do merge.

As imagens utilizam bases oficiais atualizadas e aplicam atualizações de
segurança durante a construção quando necessário:

- API/PHP Alpine: atualização controlada pela tag imutável da imagem-base;
- Web/Nginx Alpine: `apk upgrade --no-cache`;
- Fiscal Engine/Python Debian: `apt-get upgrade -y`.

A imagem-base da API não executa mais `apk upgrade` em toda publicação. Isso
evita invalidar o cache e reconstruir camadas por atualizações transitórias do
repositório Alpine. Quando o Dockerfile da base for alterado, a
`API_BASE_VERSION` deve ser incrementada; o CI valida esse contrato e publica
a nova tag imutável antes das imagens de release que a consomem.

O Trivy continua bloqueando vulnerabilidades `HIGH` e `CRITICAL` com correção disponível. A opção `ignore-unfixed` evita apenas bloqueio por vulnerabilidade para a qual o fornecedor ainda não disponibilizou correção.

A correção desta versão foi motivada por 12 vulnerabilidades `HIGH` presentes na imagem Web, incluindo pacotes `c-ares`, `curl`, `openssl`, `libexpat`, `libxml2` e `nghttp2-libs`, todos com versões corrigidas indicadas pelo scanner.
