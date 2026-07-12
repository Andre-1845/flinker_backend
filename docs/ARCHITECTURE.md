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

### Pendências a decidir com o cliente
- Regras de desempate quando múltiplos profissionais dão match no mesmo Flink.
- Enum de status do Flink (proposta: `open`, `matched`, `confirmed`, `in_progress`,
  `completed`, `cancelled` — a validar).
- Se um usuário pode acumular os perfis Professional e Company, ou é exclusivo.
- Módulo de "capacitação contínua" citado no pitch deck — fica fora do MVP por padrão até
  definição de escopo.

## Referência

Os documentos originais do projeto estão em `docs/`:
- `technical-spec-original.md` — especificação técnica completa fornecida pelo cliente
- `pitch-deck-summary.md` — resumo do pitch deck (contexto de negócio e produto)
