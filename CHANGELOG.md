# Changelog — Flinker Backend

Registro cronológico das mudanças, decisões de arquitetura e o motivo de cada uma.
Para o "estado atual" consolidado (sem histórico), ver `docs/ARCHITECTURE.md`.

## [Fase 4] — Carteira e Pagamento

**Adicionado**
- Migrations `wallets` e `transactions`.
- Models `Wallet` e `Transaction`, enums `TransactionType` (`deposit`, `withdrawal`,
  `reservation`, `refund`, `earning`, `platform_fee`) e `TransactionStatus`.
- `WalletService` — credita/debita saldo com lock pessimista (`lockForUpdate`), evitando
  corrida quando duas operações mexem no mesmo saldo ao mesmo tempo. Trata separadamente
  operações instantâneas (`credit`/`debit`) e pendentes (`createPending`/`debitPending`,
  usadas em depósito/saque que dependem de confirmação externa).
- `MercadoPagoService` — integração via API REST direta do Mercado Pago (sem SDK, usando
  `Illuminate\Support\Facades\Http`). Cria preferência de checkout (depósito) e reconsulta
  pagamentos por ID. **Nunca testado contra credenciais reais** — ver aviso no próprio
  arquivo com o passo a passo pra validar antes de produção.
- Actions: `ReserveFlinkPaymentAction` (debita o total do Flink da empresa na criação),
  `RefundFlinkReservationAction` (devolve se cancelado, com proteção contra reembolso
  duplicado), `CompleteFlinkAction` (split: profissional recebe `net_value`, margem vira
  `platform_fee` sem dono), `DepositAction`, `WithdrawAction`,
  `ProcessMercadoPagoWebhookAction`.
- Endpoints: `GET /wallet`, `POST /wallet/deposit`, `POST /wallet/withdraw`,
  `GET /transactions`, `PUT /flinks/{id}/complete`, `POST /webhooks/mercadopago` (público).
- `POST /wallet/dev-topup` — endpoint de desenvolvimento (só funciona com `APP_ENV=local`),
  credita saldo direto sem depender do Mercado Pago. Necessário porque testar o depósito
  de ponta a ponta exige um túnel público (ngrok) pro webhook, indisponível neste ambiente.
- Toda conta nova (profissional ou empresa) ganha uma `Wallet` automaticamente no cadastro.

**Decisão de produto implementada**
- Pagamento é garantido no ato da publicação do Flink: `CreateFlinkAction` debita o
  `total_value` da carteira da empresa como reserva, na mesma transação de banco que cria
  o Flink — saldo insuficiente reverte a criação inteira.

**Impacto em fases anteriores**
- ⚠️ A collection do Postman (Fase 0-3) **para de funcionar sem ajuste**: toda empresa
  agora precisa ter saldo antes de criar um Flink. Use `POST /wallet/dev-topup` antes do
  passo "Empresa cria um Flink" pra continuar testando localmente.

**Pendências conhecidas**
- Saque (`withdraw`) debita o saldo mas não completa o Pix de verdade — falta integração
  com a API de payout do Mercado Pago (exige conta business aprovada). Confirmação manual
  fica pra Fase 6 (Admin).
- `MercadoPagoService` não foi testado contra credenciais reais/sandbox.

## [Fase 3] — Match, Agenda e Check-in

**Adicionado**
- Migrations `matches` e `schedule_blocks`.
- Model `FlinkMatch` (não pôde se chamar `Match` — palavra reservada do PHP 8+).
- `MatchStatus` enum (`pending`, `accepted`, `confirmed`, `rejected`, `cancelled`).
- Actions: `ExpressInterestAction`, `AcceptMatchAction`, `ConfirmMatchAction`,
  `CancelMatchAction`, `CheckInAction`.
- `GeoDistanceService` (Haversine) e `ScheduleConflictChecker`.
- Endpoints: `POST /matches`, `PUT /matches/{id}/accept|confirm|cancel`,
  `POST /matches/{id}/checkin`, `GET /schedule`, `POST /schedule/block`.

**Decisões**
- Regra de desempate: ao aceitar um candidato, os demais `pending` do mesmo Flink
  são rejeitados automaticamente (mais simples que um ranking sofisticado no MVP).
- Confirmar um match cria um bloqueio de agenda automaticamente; cancelar um match
  `confirmed` libera esse bloqueio e reabre o Flink.
- Check-in só é permitido com o match `confirmed`, validado por raio de distância
  configurável (`FLINKER_CHECKIN_RADIUS_METERS`, padrão 150m).

**Correções**
- O filtro de geolocalização (`Flink::scopeNear`) quebrava no Postgres com erro 500:
  usava `HAVING` referenciando um alias do `SELECT` (`distance_km`), que o MySQL aceita
  mas o Postgres não. Trocado por `WHERE` repetindo a expressão completa.

## [Fase 2] — Flink, Precificação e Geolocalização

**Adicionado**
- Migration `flinks` com campos de geolocalização (`latitude`/`longitude`) e
  precificação (`net_value`, `platform_margin`, `total_value`).
- `PricingService` — isola o cálculo de margem, lê de `config('flinker.platform_margin_percent')`.
- `FlinkStatus` enum (`open`, `matched`, `confirmed`, `in_progress`, `completed`, `cancelled`).
- Endpoints: `GET/POST/PUT/DELETE /flinks`, `GET /flinks/active`, `GET /flinks/company/{id}`.

**Decisões**
- Margem fixa de 7% por enquanto (configurável via `.env`), mas isolada num serviço único
  pra facilitar trocar por regra dinâmica no futuro sem tocar no resto do sistema.
- Geolocalização confirmada no MVP (decisão do cliente), com filtro por raio via
  fórmula de Haversine direto na query.

## [Fase 1] — Perfis (User, Professional, Company)

**Adicionado**
- Campo `profile` (enum) e `is_active` na tabela `users`.
- Migrations e models `Professional` e `Company`.
- Actions `RegisterProfessionalAction` / `RegisterCompanyAction` (criam `User` + entidade
  relacionada numa transação).
- Endpoints de autenticação (`/auth/register/professional`, `/auth/register/company`,
  `/auth/login`, `/auth/logout`) e perfil (`/users/me`, `/professionals`, `/companies`).

**Decisões**
- Perfil exclusivo por conta (`professional`/`company`/`admin`) — quem quiser atuar dos
  dois lados cria duas contas.
- Cadastro e login só por email/senha no MVP (sem login social).

**Correções**
- `is_active` retornava `null` na resposta do cadastro (o valor default do banco não
  refletia no objeto Eloquent em memória) — corrigido setando explicitamente na criação.

## [Fase 0] — Setup do projeto

**Adicionado**
- Projeto Laravel 12 (ajustado de 13 pra bater com o PHP 8.2 já usado em outros projetos
  da equipe), PostgreSQL, Sanctum para autenticação de API.
- Estrutura de módulos de domínio (`app/Domain/{Professional,Company,Flink,Match,Schedule,
  Wallet,Rating,Admin,Shared}`), preservando a essência da arquitetura DDD da spec original
  sem a sobrecarga de configuração do .NET.

**Decisões**
- Trocada a stack proposta na spec original (.NET Core) por Laravel — maior domínio da
  equipe, priorizando velocidade de entrega no MVP.
- PostgreSQL como banco (mantido da spec original).
