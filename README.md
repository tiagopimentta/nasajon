# Enriquecimento de dados IBGE (CodeIgniter 3)

Aplicação PHP que cruza um CSV de municípios com a base do IBGE (cache local), gera `resultado.csv`, calcula estatísticas e envia o resumo a uma função Supabase.

**Toda a documentação de uso está neste ficheiro** (instalação, execução e testes).

---

## Requisitos

- PHP 7.4+ (recomendado 8.x), extensões `curl` e `mbstring`
- [Composer](https://getcomposer.org/) — na raiz do projeto: `composer install`
- Para os fluxos **`run`** (pipeline completo): variáveis Supabase no ambiente (ver [Variáveis de ambiente](#variáveis-de-ambiente))

---

## Instalação

Na pasta do projeto:

```bash
cd /caminho/do/nasajon
composer install
```

Configure credenciais e caminhos opcionais do CSV:

```bash
cp env.example .env
```

Edite `.env` e preencha pelo menos `SUPABASE_EMAIL`, `SUPABASE_PASSWORD` e `SUPABASE_ANON_KEY` quando for usar `ibge_process run` ou `processar run`.  
`INPUT_CSV` e `OUTPUT_CSV` são opcionais (há valores padrão no `application/config/ibge.php`).

O ficheiro `.env` na raiz é carregado pelo `index.php` (via `application/config/load_env.php`) para que `getenv()` funcione nos fluxos CLI e HTTP.

---

## Como rodar

### Linha de comando (recomendado)

Não precisa de servidor HTTP. Exporte as variáveis no mesmo terminal (ou use o `.env` já carregado ao invocar `php index.php`):

```bash
export SUPABASE_EMAIL="seu@email"
export SUPABASE_PASSWORD="sua_senha"
export SUPABASE_ANON_KEY="sua_chave_anon"

# Opcional: caminhos absolutos ou relativos ao diretório de trabalho atual
# export INPUT_CSV="/caminho/para/input.csv"
# export OUTPUT_CSV="/caminho/para/resultado.csv"

php index.php ibge_process run
# ou
php index.php processar run
```

**Saída:** mensagens no terminal; ficheiro **`resultado.csv`** (por defeito na raiz do projeto, salvo se `OUTPUT_CSV` estiver definido).

### Servidor HTTP local (navegador ou curl)

Na raiz do projeto:

```bash
composer install
php -S localhost:8080
```

O servidor embutido usa a raiz do projeto como document root. As URLs abaixo assumem **`http://localhost:8080`**.

> Em produção costuma-se ocultar `index.php` com `mod_rewrite`; aqui usa-se o URL padrão do CodeIgniter **com `index.php` explícito**.

> **Variáveis Supabase no HTTP:** o processo PHP precisa enxergá-las (por exemplo `export` no **mesmo** terminal antes de `php -S`, ou configuração do Apache/FPM).

---

## Como testar

### 1. Verificar que o projeto responde (sem Supabase)

O controller padrão é `ibge_process` (ver `application/config/routes.php`). A raiz mostra uma página HTML com lembretes de CLI:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/index.php
# Esperado: 200
```

### 2. Pré-visualizar o mapa IBGE (cache + normalização)

**Não exige** credenciais Supabase. Na primeira chamada pode descarregar o JSON do IBGE e gravar `application/cache/ibge.json`.

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/ibge` |
| **Método** | GET |
| **Retorno** | HTML com `<pre>` e `print_r` das primeiras chaves do mapa (nome normalizado → registros com `nome`, `uf`, `regiao`, `id`). |

```bash
curl -s http://localhost:8080/index.php/ibge | head -40
```

### 3. Página de boas-vindas do CodeIgniter (template padrão)

| **URL** | `http://localhost:8080/index.php/welcome` |

### 4. Pipeline completo — `ibge_process` (HTTP)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/ibge_process/run` |
| **Método** | GET |
| **Autenticação** | `SUPABASE_EMAIL`, `SUPABASE_PASSWORD`, `SUPABASE_ANON_KEY` no ambiente do PHP. |
| **O que faz** | Login Supabase → lê o CSV → IBGE → matching → `resultado.csv` → estatísticas → POST à função Supabase. |
| **Retorno** | Texto/HTML com progresso, JSON das estatísticas e resposta da API. |

**Exemplo (dois terminais):**

```bash
# Terminal 1
export SUPABASE_EMAIL="..."
export SUPABASE_PASSWORD="..."
export SUPABASE_ANON_KEY="..."
php -S localhost:8080

# Terminal 2
curl -s http://localhost:8080/index.php/ibge_process/run
```

### 5. Pipeline completo — `processar` (HTTP)

| | |
|---|---|
| **URL** | `http://localhost:8080/index.php/processar/run` |
| **Autenticação** | Mesmas variáveis Supabase. |
| **O que faz** | Usa `IbgeService`, `MatcherService`, `ProcessadorService`, `StatsService`, gera CSV e envia estatísticas à API. |
| **Retorno** | Progresso e saída da API (em browser pode aparecer dentro de `<pre>`). |

```bash
curl -s http://localhost:8080/index.php/processar/run
```

### 6. Teste só em CLI (mesmos fluxos que no browser)

```bash
export SUPABASE_EMAIL="..."
export SUPABASE_PASSWORD="..."
export SUPABASE_ANON_KEY="..."

php index.php ibge_process run
php index.php processar run
```

Após o envio, o fluxo **`processar run`** espera que a resposta JSON contenha o campo **`score`**; caso contrário o processo termina com erro.

---

## Variáveis de ambiente

| Variável | Obrigatória nos fluxos `run` | Função |
|----------|------------------------------|--------|
| `SUPABASE_EMAIL` | Sim | Login Supabase |
| `SUPABASE_PASSWORD` | Sim | Senha |
| `SUPABASE_ANON_KEY` | Sim | Chave anon (header `apikey` / token) |
| `INPUT_CSV` | Não | Caminho do CSV de entrada |
| `OUTPUT_CSV` | Não | Caminho do `resultado.csv` |
| `IBGE_DEBUG_JSON` | Não | Se definido (ex.: `1`), o fluxo `processar run` imprime o JSON do payload antes do envio à API |

Modelo para copiar: ficheiro **`env.example`** na raiz.

---

## Formato do CSV de entrada

Primeira linha com cabeçalho, exemplo:

```text
municipio,populacao
São Paulo,12300000
```

(O processamento mapeia estes campos para o enriquecimento e para as colunas de saída em `resultado.csv`.)

---

## Cache IBGE

Ficheiro: `application/cache/ibge.json`. Apague-o para forçar novo download da API.
