# Especificação Técnica Original — Flinker MVP

> Documento fornecido pelo cliente como ponto de partida. Mantido aqui como referência
> histórica. As decisões efetivamente adotadas (que às vezes divergem deste documento,
> como a escolha de stack) estão em `ARCHITECTURE.md`.

## 1. Arquitetura do Sistema (proposta original)

- Backend: .NET Core (monolito DDD modular) — **substituído por Laravel, ver ARCHITECTURE.md**
- Frontend Mobile: Flutter
- Frontend Web: React (painel admin e empresa)
- Banco de Dados: Relacional (Cloud SQL — GCP) — **adotado PostgreSQL**
- Pagamentos: Gateway externo com split
- Infraestrutura: GCP (Cloud Run, Storage, etc.)

## 2. Módulos do Domínio

Usuários, Profissionais, Empresas, Flinks, Matches, Agenda, Carteira, Transações,
Reputação, Administração.

## 3. Entidades Principais

- **User**: Id, Name, Email, PasswordHash, Profile (enum), IsActive, CreatedAt
- **Professional**: Id, UserId, Cpf, Phone, Address, PixKey, PhotoUrl, IsMei, Cnpj, Reputation
- **Company**: Id, UserId, Cnpj, ResponsibleName, ResponsibleCpf, Phone, PixKey, Reputation
- **Flink**: Id, CompanyId, ActivityType, Location, StartDateTime, EndDateTime, Requirements,
  Status, TotalValue, PlatformMargin, CreatedAt — **+ latitude/longitude (decisão nova, ver ARCHITECTURE.md)**
- **Match**: Id, FlinkId, ProfessionalId, Status, CreatedAt
- **Wallet**: Id, UserId, Balance, CreatedAt
- **Transaction**: Id, WalletId, FlinkId?, Amount, Type, Status, CreatedAt
- **Rating**: Id, FromUserId, ToUserId, FlinkId, Stars, Comment?, CreatedAt

## 4. Regras de Negócio

- Cadastro completo obrigatório para publicar/match.
- Valor calculado automaticamente pela plataforma (7% margem sobre valor líquido).
- Split de pagamento automático.
- Agenda bloqueia conflitos de horário.
- Reputação influencia prioridade no match e valor recebido.

### Serviço de Precificação (regra confirmada — margem fixa por enquanto)

1. Empresa informa o valor líquido desejado para o profissional (ex: R$ 200,00).
2. Margem da plataforma: 7% do valor líquido → R$ 14,00.
3. Valor total cobrado da empresa: R$ 214,00.
4. Profissional recebe o valor líquido informado (R$ 200,00); a margem fica embutida e
   transparente para os dois lados.

## 5. Especificação de APIs (endpoints planejados)

- **Autenticação**: `POST /auth/login`, `POST /auth/register`
- **Usuários**: `GET/PUT /users/me`
- **Profissionais**: `GET /professionals`, `GET/PUT /professionals/{id}`
- **Empresas**: `GET /companies`, `GET/PUT /companies/{id}`
- **Flinks**: `POST/GET/PUT/DELETE /flinks`, `GET /flinks/active`, `GET /flinks/company/{companyId}`
- **Matches**: `POST/GET/PUT /matches`
- **Agenda**: `GET /schedule`, `POST /schedule/block`
- **Carteira**: `GET /wallet`, `POST /wallet/deposit`, `POST /wallet/withdraw`
- **Transações**: `GET /transactions`
- **Reputação**: `POST/GET /ratings`
- **Administração**: `GET /admin/companies`, `GET /admin/professionals`, `GET /admin/flinks`,
  `PUT /admin/block-user`, `GET /admin/logs`

Todas as rotas protegidas por JWT/Sanctum, exceto `auth/*`. Respostas padronizadas com
códigos HTTP, paginação em listas, filtros e ordenação conforme necessário.

## 6. Integração Financeira

1. Empresa deposita na carteira via gateway.
2. Plataforma registra a transação.
3. Split automático: profissional + margem (7%).
4. Profissional saca via Pix.

Parceiros sugeridos: Pagar.me, Mercado Pago, Stripe — **Mercado Pago recomendado, ver
ARCHITECTURE.md**. Sem banco próprio; gateway deve suportar split e Pix; todas as
transações registradas; compliance LGPD.

## 7. Infraestrutura e DevOps

- Deploy: GCP Cloud Run / App Engine / GKE
- Banco: Cloud SQL (PostgreSQL)
- Storage: Cloud Storage para arquivos
- Logs: Cloud Logging / Stackdriver
- CI/CD: GitHub Actions ou Cloud Build (Build → Test → Deploy), ambientes Dev/Staging/Prod
- Segurança: JWT/OAuth, criptografia de dados sensíveis, controle de acesso por perfil,
  auditoria e logs administrativos, HTTPS obrigatório
