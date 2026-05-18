# AMRO IA Suporte

IA conversacional WhatsApp para suporte da AMRO Fardamentos. Responde dúvidas sobre status de pedido, código de rastreio, prazo de envio e estoque. Escala para vendedora humana quando sai do escopo.

## Stack

- PHP 8.2 (puro)
- MySQL/MariaDB
- OpenAI gpt-4o-mini
- Bling V3 API (OAuth) — dados do pedido + estoque
- Melhor Envio API — fonte de verdade do rastreio
- WooCommerce REST API — clientes
- WhatsApp Cloud API (Meta) — canal

## Estrutura

```
src/
├── Service/        ConversationService, AIAgentService, PrazoCalculatorService, ProdutoCatalogService
├── Integration/    BlingClient, MelhorEnvioClient, WoocommerceClient, OpenAIClient
├── Tool/           6 tools registradas no ToolRegistry
├── Prompt/         PromptBuilder + default.txt
├── AppFactory.php  composição dos serviços
└── bootstrap.php   dotenv + autoload + helpers (app_db, app_log)

database/migrations/  schema SQL
public/               DocRoot
cron/                 process_buffer, etc
tests/                cli_chat.php interativo + suíte de validação
```

## Setup local

```bash
composer install
cp .env.example .env   # preencher credenciais
mysql -uroot -e "CREATE DATABASE amro_ia_suporte"
mysql -uroot amro_ia_suporte < database/migrations/001_initial.sql

# Seed Bling refresh token inicial
mysql -uroot amro_ia_suporte -e "INSERT INTO bling_token (id, refresh_token, expires_at) VALUES (1, '<seu_refresh>', '2020-01-01 00:00:00')"

# Testar pipeline
php tests/test_pipeline.php
php tests/cli_chat.php
```

## Regras de negócio AMRO

- Produto PRONTA ENTREGA: envio em 1 dia útil
- Produto produção: envio em 15 dias úteis
- Pedido misto: 15 dias úteis (produção determina)

## Tools

| Nome | Função | Fonte |
|---|---|---|
| identificar_cliente | Localiza cliente | WC + Bling |
| consultar_pedido_status | Pedido detalhado | Bling |
| consultar_rastreio | Status envio + código | Melhor Envio |
| verificar_estoque | Saldo de variação | Bling (cache local) |
| calcular_prazo_envio | Data prevista | regra local |
| escalar_para_humano | Handoff | DB local |
