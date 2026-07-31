# Privacidade e dados de demonstração

O repositório não deve conter XMLs reais, relatórios de clientes, CNPJ/CPF reais,
e-mails pessoais, telefones, endereços, chaves de acesso, certificados ou nomes de
terceiros. Arquivos de demonstração devem usar dados manifestamente sintéticos e
ser identificados como não fiscais.

Dados reais são aceitos somente em ambiente privado de execução, armazenados no
S3/MinIO configurado pelo operador e protegidos pelas políticas de retenção,
acesso, backup e descarte da instalação.

Antes de cada commit, execute:

```bash
python3 scripts/scan-repository-data.py
```
