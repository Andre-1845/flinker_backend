# Flinker Backend — Arquitetura e Decisões

Este documento consolida as decisões tomadas para o desenvolvimento do backend, servindo
de referência entre sessões de trabalho.

## Stack

- **Framework**: Laravel 12 (PHP 8.2+) — alinhado com os demais projetos da equipe
- **Banco de dados**: PostgreSQL
- **Autenticação**: Laravel Sanctum (tokens de API + suporte a SPA stateful para o painel React)
- **Gateway de pagamento**: Mercado Pago (split de pagamento + PIX) — a confirmar na Fase 4
- **Deploy**: GCP (Cloud Run), conforme especificação técnica original

## Por que Laravel em vez de .NET (proposto na spec original)?

A especificação técnica original sugeria .NET Core (DDD modular). Optamos por Laravel porque:
- É a stack de maior domínio da equipe atual, priorizando velocidade de entrega no MVP.
- A estrutura de módulos de domínio (`app/Domain/*`) preserva a essência da arquitetura DDD
  da spec original, sem a sobrecarga de configuração do .NET.
- Path de migração futura pra uma stack mais rígida (NestJS ou .NET) continua aberto se a
  equipe crescer — a lógica de domínio fica isolada dos detalhes de framework.

### Por que Laravel 12 (e não 13)?

O ambiente de desenvolvimento já usa PHP 8.2 em outros projetos da equipe. O Laravel 13
exige PHP 8.3+, enquanto o Laravel 12 é compatível com PHP 8.2 — optamos por manter
consistência com o restante do parque de projetos em vez de forçar upgrade do PHP local.

## Estrutura de pastas

```
app/
  Domain/
    Professional/   # Cadastro e reputação de profissionais
    Company/         # Cadastro e gestão de empresas
    Flink/            # Demandas de trabalho (o "job post")
    Match/            # Conexão empresa-profissional
    Schedule/         # Agenda e bloqueio de horários
    Wallet/           # Saldo, depósito, saque, transações
    Rating/           # Avaliações bilaterais
    Admin/            # Gestão e auditoria administrativa
    Shared/           # Value Objects, exceptions e utilitários usados por múltiplos módulos
  Http/
    Controllers/Api/  # Controllers REST, um por módulo (a partir da Fase 1)
  Models/
    User.php          # Entidade raiz de autenticação (Professional/Company/Admin apontam pra cá)
```

Cada módulo de domínio segue a mesma subestrutura:
- `Models/` — Eloquent models e Value Objects do módulo
- `Services/` — regras de negócio que não pertencem a um único model (ex: `PricingService`)
- `Actions/` — casos de uso isolados (ex: `CreateFlinkAction`, `AcceptMatchAction`)
- `Enums/` — enums do PHP (ex: status do Flink, tipo de transação)

> **Nota**: o model do módulo `Match` se chama `FlinkMatch`, não `Match` — a palavra
> `match` é uma keyword reservada do PHP 8+ (por causa da expressão `match`) e não pode
> ser usada como nome de classe/enum/trait. O namespace `App\Domain\Match\...` continua
> normal (reserved words são permitidas em segmentos de namespace).

## Decisões de negócio confirmadas

### Precificação (Fase 2)
- **Margem fixa de 7%** sobre o valor líquido informado pela empresa, por enquanto.
- A regra fica isolada num `PricingService` único, com a margem vindo de configuração
  (`PLATFORM_DEFAULT_MARGIN_PERCENT` no `.env`, com plano de migrar para uma tabela
  `platform_settings` editável via painel admin). **Nenhum outro lugar do código deve
  calcular a margem diretamente** — sempre via esse serviço, para que trocar por uma regra
  dinâmica no futuro (por categoria, por volume, por região) não exija tocar em outras partes
  do sistema.

### Geolocalização (Fase 2 e 3)
- `Flink` armazena `latitude`/`longitude` do local do serviço.
- O aceite do Match inclui uma etapa de **check-in geolocalizado**: o profissional confirma
  presença comparando sua localização atual com a do Flink, dentro de um raio de tolerância
  configurável (sugestão inicial: 150m).

### Perfis de usuário (Fase 1 — decidido)
- Perfil é **exclusivo por conta**: cada `User` tem um único `profile` (`professional`,
  `company` ou `admin`), definido no cadastro e imutável depois (não há endpoint de troca
  de perfil no MVP). Alguém que queira atuar dos dois lados cria duas contas com emails
  diferentes. Ver `app/Domain/Shared/Enums/UserProfile.php`.
- Cadastro e login são só por email/senha por enquanto (sem login social).

### Match, Agenda e Check-in (Fase 3 — decidido)
- **Regra de desempate**: quando a empresa aceita um candidato (`Accepted`), todos os
  demais matches `Pending` no mesmo Flink são automaticamente marcados como `Rejected`.
  Simples e direto para o MVP — pode evoluir para um ranking mais sofisticado depois.
- **Fluxo de status do Match**: `Pending` (profissional demonstrou interesse) →
  `Accepted` (empresa escolheu) → `Confirmed` (profissional confirmou o aceite mútuo).
  A qualquer momento pode virar `Rejected` (não foi o escolhido) ou `Cancelled`
  (alguma das partes desistiu).
- **Agenda**: ao confirmar um match (`Confirmed`), o sistema cria automaticamente um
  bloqueio de agenda (`ScheduleBlock`) pro profissional, usando o horário do Flink.
  Antes de confirmar, o sistema verifica se já não existe um bloqueio conflitante
  (`ScheduleConflictChecker`) — se houver, a confirmação é recusada. Profissionais também
  podem criar bloqueios manuais (indisponibilidade) via `POST /schedule/block`.
- **Check-in geolocalizado**: só é possível após o match estar `Confirmed`. Compara a
  localização enviada pelo profissional com a do Flink usando a fórmula de Haversine
  (`GeoDistanceService`); se estiver fora do raio de tolerância
  (`FLINKER_CHECKIN_RADIUS_METERS`, padrão 150m), o check-in é recusado. Ao dar certo,
  o Flink muda para `in_progress`.
- **Cancelamento**: cancelar um match `Confirmed` libera o bloqueio de agenda e reabre
  o Flink (`Open`) para novos candidatos.

### Pendências a decidir com o cliente
- Módulo de "capacitação contínua" citado no pitch deck — fica fora do MVP por padrão até
  definição de escopo.
- Regra de conclusão do Flink (quem confirma a execução: empresa, profissional ou ambos?)
  e o que acontece com a reputação/pagamento nesse momento — a decidir na Fase 4/5.

## Referência

Os documentos originais do projeto estão em `docs/`:
- `technical-spec-original.md` — especificação técnica completa fornecida pelo cliente
- `pitch-deck-summary.md` — resumo do pitch deck (contexto de negócio e produto)
