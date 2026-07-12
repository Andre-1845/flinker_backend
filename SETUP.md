# Setup Local — Flinker Backend

Este projeto foi montado num ambiente sem acesso ao Packagist, então a pasta `vendor/`
(dependências do Composer) não está incluída — isso é normal e esperado (ela nunca deveria
ir pro Git). Siga os passos abaixo na sua máquina.

## Pré-requisitos

- PHP 8.2+ (testado com 8.2.4)
- Composer 2.x
- PostgreSQL 14+ rodando localmente (ou via Docker)
- Node.js 20+ (só necessário se for mexer nos assets do painel web futuramente)

## Passos

# 1. Clone o repositório (se ainda não tiver feito)

git clone https://github.com/Andre-1845/flinker_backend.git
cd flinker_backend

# 2. Instale as dependências PHP

composer install

# 3. Copie o .env de exemplo e gere a chave da aplicação

cp .env.example .env
php artisan key:generate

# 4. Configure o banco no .env (usuário/senha do seu Postgres local)

# As variáveis DB\_\* já vêm pré-configuradas para PostgreSQL, ajuste usuário/senha.

# 5. Crie o banco de dados

createdb flinker

# 6. Instale o Sanctum (autenticação de API) — publica config e prepara migrations

php artisan install:api

# 7. Rode as migrations

php artisan migrate

# 8. Suba o servidor local

php artisan serve

Depois disso, `GET http://localhost:8000/api/ping` deve responder
`{"status": "ok", "service": "flinker-api"}` — é o healthcheck da Fase 0.

## Estrutura do projeto

Veja `docs/ARCHITECTURE.md` para entender a organização dos módulos de domínio e as
decisões de negócio já tomadas (precificação, geolocalização, stack).

## Próximos passos (Fase 1)

- Migrations e models de `Professional` e `Company`
- Endpoints de perfil (`/users/me`, `/professionals`, `/companies`)
- Ajuste do `User` model com o campo `profile` (enum)

```

```
