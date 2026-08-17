# PinePet App

Base autenticada de `app.pinepet.com.br`, separada do site institucional.

## Fluxos

- `GET/POST /entrar`: login com resposta indistinguível, CSRF e limitação persistente.
- `GET/POST /definir-senha`: consome o token SHA-256 de uso único, define a senha e invalida o token na mesma transação.
- `GET/POST /primeiro-acesso`: onboarding obrigatório antes do painel.
- `GET /painel`: rota protegida, liberada somente após o onboarding.
- `POST /sair`: encerramento de sessão protegido por CSRF.

## Banco de dados

Configure o environment já existente do Dokploy e execute:

```bash
php bin/migrate.php
```

O comando aplica em ordem:

1. `001_auth_security.sql`: tentativas de login, eventos e marcador de cadastro completo.
2. `002_operational_base.sql`: clientes, endereços, pets, tutores, profissionais, fluxos, agenda e atendimentos.

Usuários e estabelecimentos permanecem nas tabelas preexistentes de autenticação e tenant; a migration operacional não redefine essas estruturas.

Ao concluir o primeiro acesso, uma única transação completa usuário e estabelecimento, cria o profissional responsável, cria o fluxo padrão, registra preferências e somente então registra `primeiro_acesso`. Qualquer falha desfaz o onboarding inteiro.

## Publicação

Execute `composer install --no-dev --optimize-autoloader` e publique somente `public/` como document root, ou use `docker compose up -d --build`.

Em produção, mantenha HTTPS, `APP_ENV=production`, `SESSION_SECURE=true` e configure `TRUSTED_PROXY_CIDRS` apenas com as redes reais do proxy. O cookie não usa domínio compartilhado.

O layout incorpora CSS, JavaScript e logo no HTML com nonce CSP, evitando dependência do roteamento de assets do provedor.
