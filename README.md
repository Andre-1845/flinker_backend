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

🚧 Fase 3 — Match + Agenda + Check-in (em andamento)

- [x] Fase 0 — Estrutura Laravel + módulos de domínio + PostgreSQL + Sanctum
- [x] Fase 1 — Perfis (User, Professional, Company): migrations, models, cadastro e login
- [x] Fase 2 — Flink, PricingService (margem fixa configurável) e filtro por geolocalização
- [x] Fase 3 — Match (interesse/aceite/confirmação), Agenda e Check-in geolocalizado
- [ ] Fase 4 — Carteira e Pagamento (Mercado Pago)
- [ ] Fase 5 — Reputação e Avaliações
- [ ] Fase 6 — Admin e Infraestrutura

## Endpoints disponíveis

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
| GET | `/api/flinks` | Sim | Lista Flinks (paginado, filtro `latitude`/`longitude`/`radius_km`) |
| GET | `/api/flinks/active` | Sim | Flinks com status `open`, mesmos filtros de geolocalização |
| GET | `/api/flinks/company/{company}` | Sim | Flinks de uma empresa específica |
| GET | `/api/flinks/{id}` | Sim | Detalhe de um Flink |
| POST | `/api/flinks` | Sim (empresa) | Cria um Flink — margem calculada automaticamente |
| PUT | `/api/flinks/{id}` | Sim (dono ou admin) | Atualiza um Flink (recalcula margem se `net_value` mudar) |
| DELETE | `/api/flinks/{id}` | Sim (dono ou admin) | Remove um Flink (só se ainda editável) |
| GET | `/api/matches` | Sim | Lista matches (filtrado pelo papel do usuário logado) |
| POST | `/api/matches` | Sim (profissional) | Demonstra interesse em um Flink (`flink_id`) |
| PUT | `/api/matches/{id}/accept` | Sim (empresa dona) | Aceita um candidato (rejeita os demais pendentes) |
| PUT | `/api/matches/{id}/confirm` | Sim (profissional) | Confirma o aceite mútuo (bloqueia a agenda) |
| POST | `/api/matches/{id}/checkin` | Sim (profissional) | Check-in geolocalizado (`latitude`, `longitude`) |
| PUT | `/api/matches/{id}/cancel` | Sim (dono ou admin) | Cancela o match (libera agenda se já confirmado) |
| GET | `/api/schedule` | Sim (profissional) | Lista os bloqueios de agenda do profissional logado |
| POST | `/api/schedule/block` | Sim (profissional) | Cria um bloqueio manual (indisponibilidade) |

Autenticação via Sanctum: envie o token retornado no login/cadastro como
`Authorization: Bearer {token}` nas rotas protegidas.
