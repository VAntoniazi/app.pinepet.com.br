# PinePet App e API v1

Aplicacao autenticada de `app.pinepet.com.br`. Este repositorio contem login, ativacao de senha por link, primeiro acesso, painel, isolamento multi-tenant, APIs e estoque transacional. O site institucional `pinepet.com.br` nao faz parte deste codigo.

## Arquitetura

- PHP 8.4 com Sodium, PostgreSQL e Nginx, sem framework.
- Controllers recebem HTTP; services concentram regras; repositories sao a unica camada de persistencia.
- Todas as consultas de dominio recebem `id_estabelecimento` e o aplicam no SQL.
- `public/` e o unico document root. Arquivos internos, migrations e `.env` nunca devem ser publicados diretamente.
- Docker executa PHP com filesystem somente leitura, `no-new-privileges`, um volume para logs e outro volume privado exclusivo para certificados cifrados.
- Layout mobile first, CSS puro e JavaScript progressivo.

### Modulos de dados

O schema canonico e sempre `pinepet`. O prefixo generico `cadastro_` foi removido e as tabelas foram agrupadas por responsabilidade:

- `acesso_usuarios`: identidade, senha e estado de acesso.
- `organizacao_*`: estabelecimento, Receita, configuracoes e horarios.
- `onboarding_*`: inscricoes, eventos, processo e importacoes.
- `clientes` e `clientes_enderecos`: relacionamento com clientes.
- `pets`, `pets_tutores` e `saude_vacinas`: animais, vinculos e saude.
- `equipe_*`: profissionais e detalhes de vinculo.
- `catalogo_*`, `agenda_*`, `atendimento_*`, `estoque_*`, `financeiro_*`, `fiscal_*`, `gateway_*`, `marketing_*` e `sistema_*`: modulos especializados.

As tabelas `blog_categorias`, `blog_autores` e `blog_posts` mantiveram nomes e comportamento. Para uma instalacao nova ou migracao integral, use somente `pinepet-completo-modular.sql`. A migration `000_modular_table_names.sql` existe para deploy incremental do app e deve executar antes das demais.

## Fluxos web

| Rota | Metodo | Funcao |
|---|---|---|
| `/entrar` | GET/POST | Login, CSRF, resposta indistinguivel e bloqueio de tentativas |
| `/definir-senha` | GET/POST | Consome token de uso unico, grava hash da senha e inicia sessao |
| `/primeiro-acesso` | GET/POST | Completa usuario, estabelecimento, profissional e fluxo padrao atomicamente |
| `/painel` | GET | Area protegida apos onboarding |
| `/sair` | POST | Revoga a sessao persistente e remove o cookie |

### Onboarding inicial

A senha sempre e definida antes do onboarding pelo link de uso unico em `/definir-senha`. Depois do primeiro login, `/primeiro-acesso` conduz um wizard mobile first com estado persistido em `onboarding_processos`:

1. Dados pessoais e pergunta sobre CNPJ. Quando existe CNPJ, a consulta ocorre somente no servidor por adapter oficial Serpro e preenche razao social, nome fantasia, situacao, natureza juridica, CNAE, endereco e contatos em `organizacao_dados_receita`.
2. Pergunta sobre aplicacao de vacinas. A resposta fica em `organizacao_configuracoes`; quando positiva, nome, CPF, CRMV, UF, validade e contatos do responsavel tecnico ficam em `saude_responsaveis_tecnicos`.
3. Pergunta sobre emissao de nota fiscal. Quem possui certificado envia um A1 `.pfx`/`.p12` de ate 5 MB e sua senha; quem nao possui acessa a oferta interna protegida em `/certificado-digital/oferta`.
4. Selecao dos meios ativos do catalogo `financeiro_metodos_pagamento`, relacionados ao tenant em `financeiro_estabelecimentos_metodos`.
5. Horarios de domingo a sabado, com dias fechados, persistidos em `organizacao_horarios`.
6. Migracao opcional de clientes, pets/tutores, produtos e servicos a partir de CSV, TSV, XLS, XLSX, XLSM, XLSB ou ODS.

Cada etapa e validada no navegador por usabilidade e novamente no servidor por seguranca. A conclusao da sexta etapa aplica a configuracao em uma unica transacao PostgreSQL; falhas nao deixam usuario, estabelecimento ou configuracoes parcialmente concluidos.

### Migracao assistida no onboarding

O arquivo e lido localmente pelo SheetJS CE 0.20.3 servido pelo proprio aplicativo; o arquivo original nao e enviado. O navegador usa somente a primeira aba, considera a primeira linha como cabecalho, mostra no maximo cinco linhas e tenta associar nomes conhecidos aos campos PinePet. O usuario pode corrigir cada associacao por seletor ou arrastando uma coluna e precisa marcar a confirmacao explicita antes de iniciar.

Depois da confirmacao, somente as colunas mapeadas sao enviadas para endpoints autenticados, em lotes de no maximo 100 linhas e payload de no maximo 512 KiB. Cada endpoint exige cookie seguro, sessao valida, CSRF e `id_estabelecimento` igual ao tenant da sessao. A barra exibe `Importacao em processo`, percentual e quantidade processada.

O servidor sanitiza e valida novamente todos os valores. Cada lote e atomico e cada linha usa savepoint: uma linha recusada nao corrompe as demais. `Idempotency-Key`, hash do payload, numero do lote e a chave derivada de tipo + hash do arquivo + mapeamento impedem repeticao com conteudo diferente e reimportacao acidental do mesmo arquivo. Os resultados e contadores ficam em `onboarding_importacoes` e `onboarding_importacao_lotes`.

Pets sao vinculados ao tutor do mesmo estabelecimento. A resolucao prioriza CPF, depois e-mail, WhatsApp e nome completo. Correspondencia ausente ou ambigua e recusada para validacao manual; nunca e criado um vinculo por aproximacao silenciosa. Por isso, importe clientes antes dos pets e, sempre que possivel, mapeie CPF, e-mail ou WhatsApp do tutor.

Limites atuais: arquivo local de 20 MB, 100.000 linhas por importacao, cinco linhas na previa, 100 linhas por lote. Fórmulas sao lidas pelo valor calculado presente no arquivo; macros nunca sao executadas. A dependencia e local, fixa e acompanhada de sua licenca em `public/assets/vendor/sheetjs/`.

#### Consulta oficial de CNPJ

A API oficial de CNPJ para pessoa juridica privada exige contratacao e credenciais do Serpro; nao e feita chamada direta do navegador. `CnpjLookupService` carrega do banco a integracao ativa, decifra o segredo somente em memoria, obtem um access token, usa HTTPS, bloqueia redirects, limita conexao a 3 segundos e resposta a 8 segundos e normaliza somente os campos aprovados. URLs nao sao aceitas do navegador, do banco ou do ambiente: a API forma os destinos a partir da combinacao `provedor + ambiente` e de uma lista fixa permitida no codigo. Isso evita SSRF e alteracao de destino por configuracao comprometida. O campo e a validacao aceitam CNPJ numerico e alfanumerico de 14 posicoes, vigente desde julho de 2026, usando ASCII menos 48 e modulo 11 para os DVs. Referencias: [servico oficial](https://www.gov.br/pt-br/servicos/obter-solucao-de-consulta-de-dados-do-cadastro-nacional-de-pessoas-juridicas-cnpj), [catalogo da API](https://www.gov.br/conecta/catalogo/apis/consulta-cnpj) e [especificacao alfanumerica](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj).

As configuracoes operacionais ficam em `sistema_integracoes`. `cliente_id`, provedor, ambiente, estado e versao ficam no PostgreSQL; `cliente_segredo_cifrado` usa Sodium `secretbox` e uma chave derivada por HKDF de `INTEGRATION_ENCRYPTION_KEY`. A tabela nao armazena a chave-raiz nem URLs arbitrarias. Alterar uma integracao incrementa sua versao e preserva os timestamps de auditoria.

Configure ou rotacione a integracao dentro do container, enviando o segredo por entrada padrao nao interativa para que ele nao apareca nos argumentos do processo nem no historico do shell:

```bash
php bin/configure-cnpj-integration.php PRODUCAO <client_id> --secret-stdin < /run/secrets/serpro_client_secret
```

O arquivo temporario do exemplo deve ser um secret montado com permissao minima e removido pelo mecanismo de secrets apos o uso. Nunca use query string, formulario web, argumento CLI ou log para transportar o `client_secret`.

#### Certificado digital

O upload aceita apenas A1 `.pfx`/`.p12`, valida a senha e a validade com OpenSSL e nunca fica em `public/`. Conteudo e senha sao cifrados separadamente com Sodium `secretbox` e `CERTIFICATE_ENCRYPTION_KEY`; o arquivo recebe nome aleatorio, permissao `0600` e reside no volume privado `app_private`. O banco guarda caminho cifrado, SHA-256, tamanho, validade e status. A chave de criptografia nao pode ser a mesma de sessao ou JWT. Backup do banco sem o volume privado, ou do volume sem a chave, nao e suficiente para restaurar a emissao fiscal.

## Modelo de seguranca

### Navegador

O navegador usa somente cookie de sessao com `Secure`, `HttpOnly`, `SameSite=Strict`, caminho `/` e sem dominio compartilhado. O ID e regenerado no login. A sessao possui timeout deslizante e limite absoluto, e seu hash e persistido em `sistema_sessoes`, permitindo expiracao e revogacao no servidor. User-Agent e vinculado por HMAC; IP e registrado por HMAC para auditoria, sem bloquear mudancas normais de rede.

A verificacao dupla da sessao usa duas derivacoes independentes por HKDF-SHA256. `session_hash` localiza a sessao usando uma chave derivada de `SESSION_HASH_KEY`; `credential_binding_hash` combina outra chave derivada, o ID da sessao e o hash Argon2/bcrypt atual da senha do usuario. A senha em claro nunca e armazenada nem usada como chave. Trocar ou refazer o hash da senha invalida automaticamente as sessoes antigas. Registros anteriores a esta regra sao revogados pela migration.

Operacoes mutaveis com sessao exigem CSRF. Formularios usam `_token`; a API de estoque usa `X-CSRF-Token`. Tokens, senhas e hashes nunca sao retornados nas APIs.

Chamadas feitas pelo navegador sempre podem ser vistas na aba Network. Nao existe forma segura de esconder uma URL utilizada pelo cliente. Portanto, JWT e segredo de integracao nao devem ser enviados ao JavaScript nem armazenados em `localStorage`; o marketplace deve chamar estas APIs pelo proprio backend.

### Integracoes backend-to-backend

O header `Authorization: Bearer <JWT>` aceita somente HS256. Sao validados assinatura, `typ`, algoritmo, emissor, audiencia, `sub`, `client_id`, `tenant_id`, `jti`, `iat`, `nbf`, `exp`, versao da credencial, TTL maximo e scopes. O cliente tambem precisa estar ativo e nao expirado em `sistema_api_clientes`; assim, um token assinado ainda pode ser recusado apos revogacao do cliente.

Cada cliente possui um `client_secret` aleatorio exibido uma unica vez. O banco guarda somente seu verificador Argon2id em `segredo_verificador`, nunca o segredo recuperavel. A chave de assinatura e unica por cliente e tenant: ela e derivada por HKDF-SHA256 da combinacao do segredo mestre, hash do `client_secret`, verificador, `client_id` e `id_estabelecimento`. Essa chave derivada e cifrada em repouso com XSalsa20-Poly1305 (`sodium_crypto_secretbox`) e `API_JWT_SECRET`; apenas o sistema consegue decifra-la para assinar e validar. O integrador utiliza o `client_secret` para provar sua identidade e recebe apenas JWTs curtos.

Rotacionar o segredo incrementa `segredo_versao`; todos os JWTs da versao anterior sao recusados imediatamente. Perder o `client_secret` exige rotacao: ele nao pode ser recuperado do banco.

O tenant do JWT, o `id_estabelecimento` recebido e o cadastro do cliente precisam ser identicos. A permissao efetiva e a intersecao entre scopes do JWT e scopes atuais do banco.

Crie o cliente diretamente por uma rotina administrativa protegida, nunca por endpoint publico:

```sql
INSERT INTO pinepet.sistema_api_clientes(client_id,id_estabelecimento,nome,scopes)
VALUES('marketplace-backend',42,'Marketplace PinePet',ARRAY['products.read','services.read','stock.read','stock.write']);
```

Emita um token curto dentro do container/backend confiavel:

```bash
php bin/rotate-api-client-secret.php marketplace-backend 42
PINEPET_API_CLIENT_SECRET='<segredo-exibido-uma-vez>' php bin/issue-api-token.php marketplace-backend 42 900
```

O primeiro comando tambem e usado para rotacao e invalida tokens anteriores. `PINEPET_API_CLIENT_SECRET` e uma variavel efemera do processo, nao deve ser cadastrada permanentemente no Dokploy, escrita em arquivo, passada como argumento CLI ou registrada em logs.

### Controles adicionais

- Permissao por usuario em `sistema_usuarios_permissoes`, por recurso e acao.
- Rate limit Nginx por IP e rate limit PostgreSQL por usuario/cliente + tenant. A chamada "defasagem 120" e implementada de forma criptograficamente definida como separacao de dominio HKDF `rate-limit-phase-120`, combinada com o tenant; nao existe operacao geometrica de 120 graus aplicavel a hashes.
- Payload maximo de 64 KiB, JSON estrito e prepared statements sem emulacao.
- CSP com nonce, HSTS, bloqueio de frame, `nosniff`, politica de permissoes e `no-store`.
- Proxy headers sao ignorados por padrao e so devem ser habilitados para CIDRs conhecidos.
- Erros internos recebem `request_id`; detalhes tecnicos ficam apenas no log.

Esses controles reduzem risco, mas nao substituem atualizacao de dependencias, backup, monitoramento, rotacao de segredos, WAF quando aplicavel e testes periodicos.

## Variaveis do Dokploy

O Dokploy contem apenas configuracao de infraestrutura e chaves-raiz que precisam existir antes de o banco ser aberto ou decifrado. Credenciais de provedores, URLs de integracao, opcoes do onboarding e dados do estabelecimento ficam no banco. Uma chave-raiz de criptografia nao deve ser armazenada no mesmo banco que protege.

| Variavel | Funcao / valor recomendado |
|---|---|
| `APP_ENV` | `production` em producao |
| `APP_DEBUG` | `false` em producao |
| `APP_URL` | `https://app.pinepet.com.br` |
| `APP_TIMEZONE` | `America/Sao_Paulo` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Conexao PostgreSQL |
| `DB_SCHEMA` | `pinepet` |
| `DB_SSLMODE` | Politica SSL do banco; prefira `require` quando suportado |
| `DB_CONNECT_TIMEOUT` | Timeout da conexao |
| `SESSION_NAME` | Nome exclusivo do cookie, ex. `pinepet_app` |
| `SESSION_SECURE` | `true` |
| `SESSION_IDLE_TIMEOUT` | Inatividade em segundos, padrao `1800` |
| `SESSION_ABSOLUTE_TIMEOUT` | Duracao maxima, padrao `43200` |
| `SESSION_TOUCH_INTERVAL` | Intervalo minimo de escrita da atividade, padrao `60` |
| `SESSION_HASH_KEY` | Chave mestra para derivacoes HKDF da sessao e vinculo com o hash da senha; minimo 32 caracteres |
| `LOGIN_MAX_ATTEMPTS` | Tentativas na janela, padrao `5` |
| `LOGIN_LOCK_MINUTES` | Janela de bloqueio, padrao `15` |
| `API_JWT_SECRET` | Chave mestra para derivar e cifrar chaves individuais dos clientes JWT; minimo 32 caracteres |
| `API_JWT_ISSUER` | Emissor esperado, padrao `pinepet-app` |
| `API_JWT_AUDIENCE` | Audiencia esperada, padrao `pinepet-api` |
| `API_JWT_MAX_TTL` | TTL maximo, limitado pelo codigo a 3600 s; recomendado `900` |
| `API_JWT_LEEWAY` | Tolerancia de relogio, maximo 60 s |
| `API_RATE_LIMIT_PER_MINUTE` | Limite por identidade + tenant, padrao `120` |
| `API_RATE_LIMIT_HASH_KEY` | Chave mestra da derivacao HKDF `rate-limit-phase-120`; minimo 32 caracteres |
| `INTEGRATION_ENCRYPTION_KEY` | Chave-raiz exclusiva para cifrar credenciais de integracoes armazenadas no banco; minimo 32 caracteres |
| `CERTIFICATE_ENCRYPTION_KEY` | Chave exclusiva para certificados A1, minimo 32 caracteres |
| `TRUST_PROXY_HEADERS` | `true` somente atras de proxy conhecido |
| `TRUSTED_PROXY_CIDRS` | CIDRs reais do proxy, separados por virgula |

Use chaves diferentes para sessao, JWT, rate limit, integracoes e certificados. Gere-as em ambiente confiavel, por exemplo `openssl rand -base64 48`, e cadastre-as como secrets no Dokploy. Nao as grave no Git, no banco ou em logs.

Rotacao das chaves mestras exige procedimento coordenado: trocar `SESSION_HASH_KEY` encerra todas as sessoes; trocar `API_JWT_SECRET` torna as chaves individuais cifradas indecifraveis e exige executar `rotate-api-client-secret.php` para todos os clientes; trocar `API_RATE_LIMIT_HASH_KEY` inicia novos buckets de limite. Nunca remova a chave JWT anterior antes de rotacionar os clientes ou preparar uma janela de migracao.

`CERTIFICATE_ENCRYPTION_KEY` tambem exige custodia e backup separados. Sua perda torna os certificados e senhas existentes irrecuperaveis; a rotacao futura deve decifrar e recifrar cada registro durante uma janela controlada, nunca simplesmente substituir a variavel.

`INTEGRATION_ENCRYPTION_KEY` segue a mesma regra: sua perda impede o uso das credenciais cifradas. A rotacao deve recifrar cada integracao antes de remover a chave anterior. As credenciais do Serpro sao administradas pelo CLI protegido e nao por variaveis permanentes do Dokploy.

## Interface mobile first

O onboarding parte de telas pequenas e cresce por media queries com `min-width`. Campos usam fonte de 16 px para evitar zoom automatico no iOS, alvos de toque possuem no minimo 48 px, o rodape de acoes respeita `safe-area-inset-bottom`, textos e grids nao provocam rolagem horizontal e o upload se adapta a 320 px. Em telas maiores, o rodape deixa de ser fixo e os formularios usam colunas quando houver espaco. A quinta etapa permite repetir rapidamente o horario de segunda-feira nos dias uteis ou na semana inteira, sem remover a edicao individual. JavaScript apenas melhora a experiencia; validacao e persistencia continuam no servidor.

## APIs

Todas as rotas abaixo exigem `id_estabelecimento` inteiro positivo. Listagens aceitam `page` (>= 1) e `per_page` (1 a 100).

| Metodo e rota | Recurso/acao | Retorno |
|---|---|---|
| `GET /api/v1/estabelecimentos` | `establishments.read` | Estabelecimento autenticado |
| `GET /api/v1/clientes` | `clients.read` | Clientes, sem CPF e segredos |
| `GET /api/v1/pets` | `pets.read` | Pets e tutores |
| `GET /api/v1/agendas` | `schedules.read` | Agendamentos |
| `GET /api/v1/atendimentos` | `attendances.read` | Atendimentos |
| `GET /api/v1/vacinas` | `vaccines.read` | Vacinas dos pets |
| `GET /api/v1/usuarios` | `users.read` | Usuarios, sem senha/tokens |
| `GET /api/v1/produtos` | `products.read` | Catalogo de produtos |
| `GET /api/v1/servicos` | `services.read` | Catalogo de servicos |
| `GET /api/v1/estoque` | `stock.read` | Saldos atual, reservado e disponivel |
| `POST /api/v1/estoque/movimentacoes` | `stock.write` | Movimentacao atomica e idempotente |
| `GET /api/v1/permissoes` | `permissions.read` | Permissoes do usuario; somente sessao |
| `GET /api/v1/permissoes/verificar` | `permissions.read` | Verifica `recurso` + `acao`; somente sessao |
| `GET /api/v1/codebook` | `codebook.read` | Catalogo de respostas |

Exemplo de leitura por sessao:

```http
GET /api/v1/clientes?id_estabelecimento=42&page=1&per_page=25
Accept: application/json
```

Exemplo backend-to-backend:

```http
GET /api/v1/produtos?id_estabelecimento=42
Authorization: Bearer eyJ...
Accept: application/json
```

### Estoque e idempotencia

Cada produto ganha um registro em `estoque_itens`. `quantidade_disponivel = quantidade_atual - quantidade_reservada`. Tipos:

- `ENTRADA`: soma ao saldo atual.
- `SAIDA`: reduz o saldo atual se houver disponivel.
- `AJUSTE`: define o saldo atual para a quantidade informada, nunca abaixo do reservado; aceita zero.
- `RESERVA`: aumenta o reservado sem alterar o atual.
- `LIBERACAO`: reduz o reservado.

Toda movimentacao exige `Idempotency-Key` de 16 a 128 caracteres. Repetir a mesma chave no mesmo tenant retorna a movimentacao original e nao altera o saldo novamente. O repository usa transacao, advisory lock por tenant/chave, `SELECT FOR UPDATE`, versao otimista, constraints e trilha imutavel.

```http
POST /api/v1/estoque/movimentacoes?id_estabelecimento=42
Content-Type: application/json
Idempotency-Key: pedido-2026-000001-saida
X-CSRF-Token: <token-da-sessao>

{"id_item":10,"tipo":"SAIDA","quantidade":"2.000"}
```

Com JWT, use `Authorization` e nao envie CSRF. Nunca reutilize a mesma chave para uma operacao diferente.

## Respostas

Sucesso:

```json
{"ok":true,"status":"accepted","code":"API-200-ACCEPTED","message":"Requisicao aceita.","data":[],"meta":{"id_estabelecimento":42,"request_id":"...","timestamp":"..."}}
```

Erro:

```json
{"ok":false,"status":"refused","code":"API-403-PERMISSION-DENIED","message":"Acesso recusado para este recurso.","details":{},"meta":{"request_id":"...","timestamp":"..."}}
```

Principais status: `400` entrada/tenant/idempotencia, `401` sessao ou JWT, `403` tenant/permissao/onboarding, `404`, `405`, `409` concorrencia/saldo, `413`, `419` CSRF, `422` validacao, `429` rate limit e `500`. O catalogo executavel esta em `/api/v1/codebook`.

## Banco e migrations

Execute antes de subir a nova versao:

```bash
php bin/migrate.php
```

Ordem:

1. `000_modular_table_names.sql`: move estruturas legadas de `public` para `pinepet` e renomeia tabelas sem copiar ou apagar dados.
2. `001_auth_security.sql`: tentativas e eventos de autenticacao.
3. `002_operational_base.sql`: onboarding e dominio operacional.
4. `003_api_permissions_catalogs.sql`: permissoes, vacinas, produtos e servicos.
5. `004_security_sessions_stock.sql`: sessoes vinculadas ao hash da senha, clientes JWT com credenciais cifradas, rate limits e estoque. Ao introduzir o vinculo de credencial, sessoes antigas sem `credential_binding_hash` sao revogadas intencionalmente.
6. `005_onboarding_business_setup.sql`: processo em etapas, dados oficiais de CNPJ, responsavel tecnico, fiscal, catalogo de pagamentos e horarios.
7. `006_integration_configuration.sql`: credenciais de integracoes cifradas no banco, ambiente/provedor controlados e versionamento de configuracao.
8. `007_onboarding_data_import.sql`: trabalhos e lotes idempotentes da migracao assistida de clientes, pets, produtos e servicos.
9. `008_pet_classification_catalogs.sql`: catálogos padronizados de espécies, raças e portes, relacionados aos pets e usados na importação do primeiro acesso.
10. `009_client_profile_catalogs.sql`: data de nascimento e catálogo controlado de sexo para clientes, usados na importação do primeiro acesso.
11. `010_numeric_document_integrity.sql`: CPF somente numérico, CNPJ alfanumérico canônico, saneamento transacional e validação dos dígitos verificadores.

O onboarding e a movimentacao de estoque sao transacionais: qualquer falha desfaz a operacao inteira. Migrations devem rodar uma unica vez por release, com backup testado antes de producao.

## Deploy

1. Configure no Dokploy somente infraestrutura e chaves-raiz; nao crie `.env` no repositorio.
2. Faca backup e execute as migrations.
3. Configure a integracao CNPJ pelo CLI protegido depois da migration `006`.
4. Rode `composer install --no-dev --optimize-autoloader` se houver dependencias.
5. Construa e publique: `docker compose up -d --build`.
6. Confirme HTTPS, health do banco, login, expiracao, onboarding em 320 px ou mais, `401/403/404/405/419/429`, tenant divergente e repeticao da idempotency key.
7. Agende limpeza de registros expirados de `sistema_sessoes`, `sistema_api_rate_limits` e tentativas antigas conforme a politica de retencao.

Nao publique `.git`, `.env`, `database/`, `storage/` ou o codigo PHP como arquivos estaticos. Nao registre JWT, cookie, senha, token de ativacao ou chaves de idempotencia com dados pessoais em logs.
