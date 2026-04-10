# Enriquecimento de dados IBGE (CodeIgniter 3)

Aplicação PHP que cruza um CSV de municípios com a base do IBGE (cache local), gera `resultado.csv`, calcula estatísticas e envia o resumo a uma função Supabase.

---

## Requisitos rápidos

- PHP 7.4+ (recomendado 8.x), extensões `curl` e `mbstring`
- Composer (`composer install` na raiz)
- Variáveis de ambiente para os fluxos que autenticam e enviam à API (ver abaixo)

---

## Subir o projeto para testar no navegador

Na pasta do projeto:

```bash
cd /caminho/do/projeto
composer install
php -S localhost:8080
```

O servidor embutido usa a raiz do projeto como document root. As URLs abaixo assumem base **`http://localhost:8080`**. Troque host/porta se usar Apache/Nginx.

> **Nota:** Em produção costuma-se ocultar `index.php` com `mod_rewrite`; aqui usamos o formato padrão do CodeIgniter com **`index.php` explícito**.

---

## Testando os “endpoints” (rotas HTTP)

Esta aplicação **não expõe uma API REST JSON** para o fluxo principal: os controllers devolvem **HTML** (ou texto dentro de `<pre>`). Use o navegador ou `curl` para inspecionar a resposta.

### 1. Página inicial / ajuda (`ibge_process`)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php` ou `http://localhost:8080/index.php/ibge_process` ou `.../ibge_process/index` |
| **Método** | GET |
| **Autenticação** | Não |
| **O que faz** | Rota padrão (`routes.php`): exibe página HTML com instruções de CLI e caminhos de `input.csv` / `resultado.csv`. |
| **Retorno** | `Content-Type: text/html` — documento HTML com título “Enriquecimento IBGE”. |

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/index.php
# Esperado: 200
```

---

### 2. Amostra do mapa IBGE (cache + normalização)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/ibge` |
| **Método** | GET |
| **Autenticação** | Não |
| **O que faz** | Carrega `IbgeService`, monta o mapa de municípios (nome normalizado → lista de registros) e imprime as **5 primeiras chaves** com `print_r`. Na primeira requisição pode baixar o JSON do IBGE e gravar `application/cache/ibge.json`. |
| **Retorno** | `Content-Type: text/html; charset=UTF-8` — bloco `<pre>` com estrutura PHP (arrays aninhados). Cada entrada contém `nome`, `uf`, `regiao`, `id` por município. |

```bash
curl -s http://localhost:8080/index.php/ibge | head -40
```

---

### 3. Página de boas-vindas (CodeIgniter)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/welcome` |
| **Método** | GET |
| **Autenticação** | Não |
| **Retorno** | HTML da view `welcome_message` (template padrão do framework). |

---

### 4. Pipeline completo — `ibge_process` (HTTP)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/ibge_process/run` |
| **Método** | GET |
| **Autenticação** | Indireta: exige `SUPABASE_EMAIL`, `SUPABASE_PASSWORD`, `SUPABASE_ANON_KEY` no ambiente do PHP (senão mensagem de erro e saída com código 1). |
| **O que faz** | Login Supabase → lê CSV → IBGE → `Municipio_matcher` → grava `resultado.csv` → estatísticas → POST para a função Supabase. |
| **Retorno** | Texto/HTML no corpo: mensagens de progresso, JSON das estatísticas (inclui `total_ambiguo`), depois resposta da API (`score`, `feedback` ou JSON bruto). |

**Como testar com variáveis no mesmo shell:**

```bash
export SUPABASE_EMAIL="..."
export SUPABASE_PASSWORD="..."
export SUPABASE_ANON_KEY="..."
php -S localhost:8080
# Em outro terminal:
curl -s http://localhost:8080/index.php/ibge_process/run
```

> Em ambiente web, o PHP precisa “enxergar” essas variáveis (export antes de subir o `php -S`, ou configuração do Apache/FPM).

---

### 5. Pipeline completo — `processar` (HTTP)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/processar/run` |
| **Método** | GET |
| **Autenticação** | Mesmas variáveis Supabase que o item anterior. |
| **O que faz** | Usa `IbgeService` (mapa com cache), `MatcherService`, `ProcessadorService`, `StatsService`, gera CSV e envia stats à API. |
| **Retorno** | Texto/HTML: progresso, depois `print_r` da resposta da API (array PHP serializado como texto no CLI; em browser, dentro de `<pre>` quando aplicável). |

```bash
curl -s http://localhost:8080/index.php/processar/run
```

---

## Linha de comando (recomendado para prova/automação)

Não depende de servidor HTTP; credenciais vêm do ambiente do shell.

```bash
export SUPABASE_EMAIL="..."
export SUPABASE_PASSWORD="..."
export SUPABASE_ANON_KEY="..."
# Opcional: INPUT_CSV, OUTPUT_CSV

php index.php ibge_process run
# ou
php index.php processar run
```

**Saída:** fluxo impresso no terminal; `resultado.csv` no caminho configurado (por padrão na raiz do projeto, ou `OUTPUT_CSV`).

---

## Variáveis de ambiente (resumo)

| Variável | Obrigatória nos fluxos `run` | Função |
|----------|-------------------------------|--------|
| `SUPABASE_EMAIL` | Sim | Login Supabase |
| `SUPABASE_PASSWORD` | Sim | Senha |
| `SUPABASE_ANON_KEY` | Sim | Chave anon (header `apikey` no token) |
| `INPUT_CSV` | Não | Caminho do CSV de entrada |
| `OUTPUT_CSV` | Não | Caminho do `resultado.csv` |
| `IBGE_DEBUG_JSON` | Não | Se definido (ex.: `1`), o fluxo `processar run` imprime o JSON do payload antes do envio à API |

Após o envio, o fluxo **`processar run`** exige que a resposta JSON contenha o campo **`score`**; caso contrário o processo encerra com erro.

---

## Formato do CSV de entrada

Primeira linha com cabeçalho:

```text
municipio,populacao
São Paulo,12300000
```

---

## Cache IBGE

Arquivo: `application/cache/ibge.json`. Apague o arquivo para forçar novo download da API.

---

## Documentação adicional

- Detalhes técnicos do código: `INSTRUCOES.md`
