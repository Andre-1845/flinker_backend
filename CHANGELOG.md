# Changelog — Flinker Backend

Registro cronológico das mudanças, decisões de arquitetura e o motivo de cada uma.
Para o "estado atual" consolidado (sem histórico), ver `docs/ARCHITECTURE.md`.

## [Auditoria 2026-10-01] — Teste guiado E2E, margem configurável, carteira real e upload de foto

**Contexto**

- Segunda rodada de auditoria (continuação da rodada anterior, 28/08): teste manual
  guiado de ponta a ponta, com o cliente operando o app ao vivo e cada sintoma
  verificado contra código/banco/log real antes de ser tratado como bug. Relatório
  completo em `claude/auditoria_flinker_261001.md` (projeto Flinker, Claude).

**Adicionado**

- Tabela `settings` (chave/valor genérica) + `SettingsService` + recurso Filament
  "Configurações" — a margem da plataforma (`platform_margin_percent`) deixa de ser
  fixa em `config/flinker.php`/`.env` e passa a ser editável em runtime pelo painel
  admin. `PricingService` resolve o serviço via `app()` (não no construtor) e tem
  fallback seguro pro valor de config caso a tabela ainda não exista — preserva a
  compatibilidade com `PricingServiceTest`, que instancia `new PricingService()`
  direto, sem container, contra SQLite em memória sem migrations.
- Endpoint `GET /settings/platform-margin` — o frontend parou de hardcodar o
  percentual (estava divergente: 8% mostrado pra empresa vs 7% realmente cobrado).
- Endpoint `POST /professionals/{id}/photo` — upload de foto de perfil nunca tinha
  existido no backend (só aceitava `photo_url` como string pronta). Valida imagem
  até 5MB, apaga a foto antiga do disco `public`, salva a nova.
- `ProfessionalResource` passa a expor `name` (via relação `user`) e
  `MatchController::index` carrega `professional.user` — a empresa agora vê o nome
  do candidato na tela de Matches.

**Correções**

- `config/app.php`: timezone trocado de `UTC` pra `America/Sao_Paulo` — bloqueava a
  criação de Flink no mesmo dia.
- `bootstrap/app.php`: `AuthenticationException` passa a devolver JSON 401 em vez de
  tentar `route('login')` (que não existe num app 100% API) — mas **só** pras rotas
  `/api/*`; o guard `admin` do Filament continua com seu próprio redirect pra
  `/admin/login`. A primeira versão desse fix interceptava toda `AuthenticationException`
  e quebrou esse redirect — pego pelo próprio PHPUnit do Andre
  (`AdminPanelAccessTest::guest_is_redirected_to_login`), corrigido no commit seguinte.

**Validado**

- PHPUnit completo rodado pelo Andre: 27 passed, 0 failed (`PricingServiceTest`,
  `WalletServiceTest`, `AdminPanelAccessTest`, `TransactionApprovalTest`,
  `FlinkCompletionFlowTest`, `MercadoPagoWebhookSignatureTest`, entre outros).
- Teste guiado ao vivo: margem editada no Filament refletindo no app; upload de foto
  com recorte persistindo entre sessões; saldo/extrato reais nos dois lados; saque via
  Pix debitando na hora e ficando pendente até aprovação manual.

**Pendências conhecidas**

- `php artisan storage:link` precisa ser executado em todo ambiente novo (local,
  staging, produção) — sem isso a URL da foto de perfil retorna 404 mesmo com o
  upload salvo corretamente.
- Sugestão de produto registrada, não implementada: ocultar da visualização os Flinks
  já concluídos (pedido do Andre durante o teste).

## [Auditoria] — Hardening de Pagamento, Conclusão Dupla, Painel Admin (Filament) e Docker

**Contexto**

- Auditoria completa do backend e frontend (relatório publicado à parte), com plano de
  correção aprovado e implementado diretamente no código.

**Adicionado**

- `PaymentGatewayInterface` (via `PaymentGatewayServiceProvider`) — abstrai o provedor de
  pagamento atrás de uma interface, pra trocar de provedor (hoje Mercado Pago) sem tocar
  em Actions/Controllers. Requisito de flexibilidade do produto.
- Verificação de assinatura do webhook do Mercado Pago (`x-signature`, HMAC-SHA256) antes
  de processar qualquer notificação.
- `pix_key` (Professional/Company) criptografado em repouso (cast `encrypted`) — migration
  `widen_pix_key_columns_to_text` (o cast aumenta o tamanho necessário da coluna).
- CORS explícito (`config/cors.php`), substituindo o default permissivo do framework.
- Rate limiting: `throttle:6,1` em `/auth/*`, `throttle:30,1` no webhook do Mercado Pago.
- Fluxo de esqueci/redefinir senha via Password Broker do Laravel.
- Conclusão dupla do Flink: profissional confirma em
  `PUT /matches/{id}/confirm-completion`, empresa confirma em `PUT /flinks/{id}/complete`
  — o split de pagamento só roda com os dois confirmados, ou após o prazo automático
  (`FLINKER_AUTO_COMPLETE_HOURS`, padrão 48h, comando agendado `flinks:auto-complete`).
- Painel admin via Filament em `/admin`, bootstrap só por CLI (`flinker:make-admin`), sem
  tela de cadastro pública. Resources: `UserResource` (bloqueio/desbloqueio),
  `TransactionResource` (aprovação/rejeição de saque), `FlinkResource`.
- Dockerfile (PHP-FPM + Nginx) e `docker-compose.yml`, alinhados ao template
  "Ubuntu 24.04 with Docker" da Hostinger.
- 27 testes novos (Feature + Unit), 0 falhas.

**Decisão de produto implementada**

- Conclusão do Flink exige confirmação dos dois lados (ou prazo automático) em vez de um
  lado só marcar como concluído — reduz risco de disputa sobre o pagamento garantido.
- Painel admin é uma tela separada no backend (Filament/PHP), não React — evita duplicar
  autenticação/autorização de admin no frontend.

**Correções**

- Bug pré-existente de resolução de factory para models em namespace de domínio (DDD) —
  corrigido em `AppServiceProvider`.

**Pendências conhecidas**

- ~~`composer.lock` gerado em ambiente PHP 8.4~~ — resolvido: padronizamos o projeto em
  PHP 8.4 (local + Docker), `composer update` rodado direto no PHP 8.4 real. Ver detalhes
  no commit desta correção.
- Chave anon do Supabase não rotacionada no histórico do git do frontend (risco baixo,
  Supabase sendo desativado).
- `docker compose build` validado só parcialmente (sem acesso de rede completo no ambiente
  onde foi construído).

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
