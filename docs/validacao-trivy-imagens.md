# Validação Trivy das imagens

A Pull Request constrói e executa as imagens `linux/amd64` antes do merge.

As imagens aplicam as atualizações de segurança disponíveis nos repositórios oficiais:

- API/PHP Alpine: `apk upgrade --no-cache`;
- Web/Nginx Alpine: `apk upgrade --no-cache`;
- Fiscal Engine/Python Debian: `apt-get upgrade -y`.

O Trivy continua bloqueando vulnerabilidades `HIGH` e `CRITICAL` com correção disponível. A opção `ignore-unfixed` evita apenas bloqueio por vulnerabilidade para a qual o fornecedor ainda não disponibilizou correção.

A correção desta versão foi motivada por 12 vulnerabilidades `HIGH` presentes na imagem Web, incluindo pacotes `c-ares`, `curl`, `openssl`, `libexpat`, `libxml2` e `nghttp2-libs`, todos com versões corrigidas indicadas pelo scanner.
