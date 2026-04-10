# Instruções do projeto — enriquecimento de dados IBGE

Este documento resume o que foi implementado e como usar, para explicar a outras pessoas (ou para você mesmo no futuro) sem precisar reabrir o código inteiro.

## Objetivo

Processar um CSV com municípios e população, cruzar com a base oficial do IBGE, gerar um CSV enriquecido, calcular estatísticas e enviar essas estatísticas para um endpoint Supabase autenticado.

## Stack

- **Backend:** PHP com **CodeIgniter 3.1.x**
- **HTTP:** extensão `cURL` (recomendada) para chamadas à API do IBGE, login Supabase e envio do JSON
- **Texto:** `mbstring`; `intl` opcional (melhora transliteração na normalização de nomes)

## Estrutura relevante

| Caminho | Descrição |
|--------|-----------|
| `index.php` | Front controller do CodeIgniter; ajuste de `error_reporting` para PHP 8.2+ em modo development |
| `application/config/ibge.php` | URLs (IBGE, submit), leitura de credenciais e caminhos de CSV via `getenv()` |
| `application/config/routes.php` | Controller padrão: `ibge_process` |
| `application/controllers/Ibge_process.php` | Orquestra o pipeline (`index` = página de ajuda; `run` = execução completa) |
| `application/libraries/Text_normalizer.php` | Normalização (transliteração + minúsculas + trim) para matching |
| `application/libraries/Ibge_client.php` | Download e normalização dos campos do JSON do IBGE |
| `application/libraries/Supabase_auth.php` | Login (`grant_type=password`) e POST das estatísticas |
| `application/libraries/Municipio_matcher.php` | Match exato por nome normalizado; fallback com similaridade (`similar_text`) |
| `application/libraries/Ibge_stats.php` | Contagens, população total onde status OK, médias por região, payload para a API |
| `input.csv` | Entrada esperada (colunas `municipio`, `populacao`) |
| `resultado.csv` | Saída gerada na raiz do projeto (sobrescrito a cada execução bem-sucedida) |
| `env.example` | Nomes das variáveis de ambiente necessárias |

## Fluxo de execução (resumo)

1. **Login** no Supabase com e-mail, senha e `apikey` (anon), obtendo `access_token`.
2. **Leitura** do CSV de entrada.
3. **Carga** da lista de municípios do IBGE (API oficial).
4. **Normalização** dos nomes (entrada e base IBGE) para comparação.
5. **Matching:** tentativa de igualdade exata; se não houver um único município, uso de candidatos por similaridade; status `OK`, `NAO_ENCONTRADO` ou `AMBIGUO`.
6. **Gravação** de `resultado.csv` com colunas: município/população de entrada, dados IBGE (nome, UF, região, id), status.
7. **Estatísticas:** totais por status, soma de população onde `OK`, média de população por região (apenas `OK`).
8. **Envio** em JSON para a função Supabase (`stats` no corpo), cabeçalho `Authorization: Bearer <token>`.
9. **Saída** no terminal: JSON das estatísticas completas (inclui `total_ambiguo` para conferência local) e resposta da API (`score` / `feedback` quando existirem).

## Como rodar

### Linha de comando (recomendado para o processamento)

```bash
cd /caminho/do/projeto
export SUPABASE_EMAIL="seu@email"
export SUPABASE_PASSWORD="sua_senha"
export SUPABASE_ANON_KEY="sua_chave_anon"
# Opcional:
# export INPUT_CSV="/caminho/input.csv"
# export OUTPUT_CSV="/caminho/resultado.csv"

php index.php ibge_process run
```

### Navegador

Abrir a raiz do site apontando para este projeto: a rota padrão mostra instruções. O processamento pesado foi pensado para **CLI** (`run`).

## Variáveis de ambiente

| Variável | Obrigatória | Função |
|----------|-------------|--------|
| `SUPABASE_EMAIL` | Sim | Usuário do login |
| `SUPABASE_PASSWORD` | Sim | Senha |
| `SUPABASE_ANON_KEY` | Sim | Header `apikey` no endpoint de token |
| `INPUT_CSV` | Não | Caminho do CSV de entrada (padrão: `input.csv` na raiz) |
| `OUTPUT_CSV` | Não | Caminho do CSV de saída (padrão: `resultado.csv` na raiz) |

## Dependências PHP (Composer)

Na raiz existe `composer.json` (framework CodeIgniter e PHPUnit para testes do próprio framework). Após clonar, instalar com:

```bash
composer install
```

A pasta `vendor/` não precisa ser versionada se o `.gitignore` já a ignorar; o que importa para reproduzir o ambiente é `composer.json` / `composer.lock` conforme a política do time.

## Observações técnicas

- **PHP 8.2+:** o núcleo do CodeIgniter 3 ainda dispara avisos de depreciação (propriedades dinâmicas). Em `development`, o `index.php` reduz ruído omitindo `E_DEPRECATED` apenas para facilitar a leitura dos erros reais.
- **Matching:** usa-se `similar_text` com limiar configurável na biblioteca, adequado para nomes de municípios.
- **Segurança:** não commitar credenciais; usar apenas variáveis de ambiente ou segredos do servidor.
