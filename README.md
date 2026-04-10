# Enriquecimento IBGE (PHP / CodeIgniter 3)

Lê `input.csv`, cruza com dados do IBGE e gera `resultado.csv`, depois envia estatísticas para uma API (retorna `score`).

## Como rodar

```bash
composer install
cp env.example .env   # edite e coloque SUPABASE_EMAIL, SUPABASE_PASSWORD e SUPABASE_ANON_KEY
php index.php processar run
```

- Entrada: `application/data/input.csv` (10 linhas da prova); se não existir, usa `input.csv` na raiz ou `INPUT_CSV`. Cabeçalho exato: `municipio,populacao`.
- Saída padrão: `resultado.csv` na raiz.

Precisa de PHP 8.0+ com `curl` e `mbstring`.
