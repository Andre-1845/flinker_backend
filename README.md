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

🚧 Fase 0 — Setup do projeto (em andamento)

- [x] Estrutura Laravel + módulos de domínio
- [x] Configuração PostgreSQL
- [x] Sanctum configurado para autenticação de API
- [ ] Fase 1 — Perfis (User, Professional, Company)
- [ ] Fase 2 — Flink + Precificação + Geolocalização
- [ ] Fase 3 — Match + Agenda + Check-in
- [ ] Fase 4 — Carteira e Pagamento (Mercado Pago)
- [ ] Fase 5 — Reputação e Avaliações
- [ ] Fase 6 — Admin e Infraestrutura
