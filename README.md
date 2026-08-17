# PinePet App

Base autenticada de `app.pinepet.com.br`, separada do site institucional.

## Fluxos disponíveis

- `GET/POST /entrar`: login com resposta indistinguível, CSRF e limitação persistente.
- `GET/POST /definir-senha`: consome o token SHA-256 criado pelo cadastro, define a senha e invalida o token na mesma transação.
- `GET /painel`: rota protegida e ponto inicial para os módulos.
- `GET/POST /primeiro-acesso`: complemento cadastral obrigatório antes do painel.
- `POST /sair`: encerra a sessão com validação CSRF.

## Instalação

1. Copie `.env.example` para `.env` e use as credenciais PostgreSQL de menor privilégio.
2. Aplique `database/001_auth_security.sql` na base PinePet; ela também adiciona o marcador transacional de cadastro completo e os eventos de autenticação.
3. Execute `composer install --no-dev --optimize-autoloader`.
4. Publique somente `public/` como document root ou use `docker compose up -d --build`.

O serviço Nginx usa uma imagem própria e incorpora `public/` durante o build. Depois de alterar CSS, JavaScript ou imagens, faça um novo build; não reutilize apenas o contêiner antigo, pois os assets são imutáveis e versionados.

Em produção, mantenha `APP_ENV=production`, `SESSION_SECURE=true`, HTTPS obrigatório e configure `TRUSTED_PROXY_CIDRS` somente com as redes reais do proxy. O cookie não usa domínio compartilhado, isolando a sessão no subdomínio.

## Arquitetura

`public/index.php` é o único ponto de entrada. Controllers coordenam o fluxo, repositories isolam SQL, Core concentra sessão/roteamento/CSRF e views não acessam banco. Novos módulos devem entrar como controllers, services, repositories, views e rotas protegidas por `Auth::requireUser()`.
