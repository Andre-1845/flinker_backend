# Flinker Backend

Backend do Flinker — infraestrutura digital para trabalho sob demanda no Brasil, conectando
empresas a profissionais para serviços curtos, com match, agenda, pagamento via PIX e
reputação.

## Stack

Laravel 12 (PHP 8.2+) · PostgreSQL · Sanctum (auth) · Mercado Pago (pagamentos, a confirmar)

## Documentação

- [`SETUP.md`](./SETUP.md) — como rodar o projeto localmente
- [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) — arquitetura, módulos de domínio e decisões de negócio
- [`docs/technical-spec-original.md`](./docs/technical-spec-original.md) — especificação técnica original
- [`docs/pitch-deck-summary.md`](./docs/pitch-deck-summary.md) — contexto de produto e negócio

## Status

🚧 Fase 1 — Perfis (em andamento)

- [x] Fase 0 — Estrutura Laravel + módulos de domínio + PostgreSQL + Sanctum
- [x] Fase 1 — Perfis (User, Professional, Company): migrations, models, cadastro e login
- [ ] Fase 2 — Flink + Precificação + Geolocalização
- [ ] Fase 3 — Match + Agenda + Check-in
- [ ] Fase 4 — Carteira e Pagamento (Mercado Pago)
- [ ] Fase 5 — Reputação e Avaliações
- [ ] Fase 6 — Admin e Infraestrutura

## Endpoints disponíveis (Fase 1)

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| GET | `/api/ping` | Não | Healthcheck |
| POST | `/api/auth/register/professional` | Não | Cadastro de profissional |
| POST | `/api/auth/register/company` | Não | Cadastro de empresa |
| POST | `/api/auth/login` | Não | Login (retorna token Sanctum) |
| POST | `/api/auth/logout` | Sim | Encerra a sessão/token atual |
| GET | `/api/users/me` | Sim | Dados do usuário autenticado |
| PUT | `/api/users/me` | Sim | Atualiza nome/email |
| GET | `/api/professionals` | Sim | Lista profissionais (paginado, filtro `min_reputation`) |
| GET | `/api/professionals/{id}` | Sim | Detalhe de um profissional |
| PUT | `/api/professionals/{id}` | Sim | Atualiza o próprio perfil (ou admin) |
| GET | `/api/companies` | Sim | Lista empresas (paginado, filtro `min_reputation`) |
| GET | `/api/companies/{id}` | Sim | Detalhe de uma empresa |
| PUT | `/api/companies/{id}` | Sim | Atualiza a própria empresa (ou admin) |

Autenticação via Sanctum: envie o token retornado no login/cadastro como
`Authorization: Bearer {token}` nas rotas protegidas.
