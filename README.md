# listando dados do IBGE (PHP / CodeIgniter 3)

Lê `input.csv`, cruza com dados do IBGE e gera `resultado.csv`, depois envia estatísticas para uma API (retorna `score`).

## Como rodar

```bash
composer install
cp env.example .env   # edite e coloque SUPABASE_EMAIL, SUPABASE_PASSWORD e SUPABASE_ANON_KEY
php index.php processar run
```

- Entrada padrão: `input.csv` na raiz do projeto (`municipio`, `populacao` no cabeçalho).
- Saída padrão: `resultado.csv` na raiz.

Precisa de PHP 8.0+ com `curl` e `mbstring`.
