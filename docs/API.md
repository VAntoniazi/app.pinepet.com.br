# API PinePet v1

A referencia completa de autenticacao, variaveis, endpoints, scopes, estoque, idempotencia, respostas e deploy esta no [README](../README.md#apis).

Regras invariantes:

- `id_estabelecimento` e obrigatorio em toda rota e precisa coincidir com a sessao/JWT e o cadastro da identidade.
- Navegadores usam cookie `HttpOnly`; JWT e exclusivo para integracao backend-to-backend.
- Permissao de sessao e validada no banco. JWT exige scope no token e no cliente ativo.
- Escritas por sessao exigem CSRF; movimentacoes exigem tambem `Idempotency-Key`.
- O servidor valida formato, tamanho e dominio de cada entrada; dados do cliente nunca sao considerados confiaveis.
