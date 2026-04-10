<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pipeline de enriquecimento com `Municipio_matcher` e `Ibge_stats` (inclui status AMBIGUO no resumo).
 *
 * CLI: `php index.php ibge_process run`
 */
class Ibge_process extends CI_Controller {

	/**
	 * Carrega configuração `ibge` e bibliotecas do pipeline (cliente IBGE, Supabase, matcher, stats).
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->config('ibge');
		$this->load->library('Ibge_client');
		$this->load->library('Supabase_auth');
		$this->load->library('Municipio_matcher');
		$this->load->library('Ibge_stats');
	}

	/**
	 * Página HTML com instruções de uso do processamento via linha de comando.
	 *
	 * @return void
	 */
	public function index()
	{
		$this->output->set_content_type('text/html', 'UTF-8');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>IBGE enrichment</title></head><body>';
		echo '<h1>Enriquecimento IBGE (CodeIgniter 3)</h1>';
		echo '<p>Execute o processamento na linha de comando:</p>';
		$root = rtrim(FCPATH, DIRECTORY_SEPARATOR);
		echo '<pre>cd ' . htmlspecialchars($root, ENT_QUOTES, 'UTF-8') . "\n";
		echo 'export SUPABASE_EMAIL=... SUPABASE_PASSWORD=... SUPABASE_ANON_KEY=...' . "\n";
		echo 'php index.php ibge_process run</pre>';
		echo '<p>Arquivos: <code>input.csv</code> → <code>resultado.csv</code> (raiz do projeto).</p>';
		echo '</body></html>';
	}

	/**
	 * Executa o pipeline completo: login Supabase, leitura do CSV, carga IBGE, matching,
	 * gravação de `resultado.csv`, cálculo de estatísticas e envio à API.
	 *
	 * Adequado para CLI (`php index.php ibge_process run`); em HTTP imprime a mesma saída em texto.
	 *
	 * @return void
	 */
	public function run()
	{
		$api_errors = 0;

		$email = trim((string) $this->config->item('supabase_email'));
		$password = (string) $this->config->item('supabase_password');
		$apikey = trim((string) $this->config->item('supabase_anon_key'));

		if ($email === '' || $password === '' || $apikey === '')
		{
			$this->_fail('Defina SUPABASE_EMAIL, SUPABASE_PASSWORD e SUPABASE_ANON_KEY (ambiente ou config).');
		}

		$input_csv = $this->config->item('input_csv');
		$output_csv = $this->config->item('output_csv');
		$submit_url = $this->config->item('ibge_submit_url');

		$this->_out('Autenticando no Supabase...');
		try
		{
			$token = $this->supabase_auth->login($email, $password, $apikey);
		}
		catch (Exception $e)
		{
			$this->_fail('Falha no login: ' . $e->getMessage());
		}

		$this->_out('Lendo ' . $input_csv . '...');
		try
		{
			$registros = $this->_read_csv($input_csv);
		}
		catch (Exception $e)
		{
			$this->_fail('Erro ao ler CSV: ' . $e->getMessage());
		}

		$this->_out('Carregando municípios do IBGE...');
		try
		{
			$raw = $this->ibge_client->fetch_municipios();
			$ibge_flat = array();
			foreach ($raw as $m)
			{
				$ibge_flat[] = $this->ibge_client->flatten_municipio($m);
			}
		}
		catch (Exception $e)
		{
			$api_errors++;
			$this->_fail('Erro na API do IBGE: ' . $e->getMessage());
		}

		$this->_out('Enriquecendo registros (match)...');
		$enriched = $this->municipio_matcher->enrich_records($registros, $ibge_flat);

		$this->_out('Gravando ' . $output_csv . '...');
		$this->_write_result_csv($output_csv, $enriched);

		$full_stats = $this->ibge_stats->compute_stats($enriched, $api_errors);
		$payload = $this->ibge_stats->stats_for_api_payload($full_stats);

		$this->_out('');
		$this->_out('--- Estatísticas (resumo) ---');
		$this->_out(json_encode($full_stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

		$this->_out('');
		$this->_out('Enviando estatísticas para a API...');
		try
		{
			$resposta = $this->supabase_auth->submit_stats($token, $submit_url, $payload);
		}
		catch (Exception $e)
		{
			$api_errors++;
			$full_stats = $this->ibge_stats->compute_stats($enriched, $api_errors);
			$this->_out('Falha no envio: ' . $e->getMessage());
			$this->_out(json_encode($this->ibge_stats->stats_for_api_payload($full_stats), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
			$this->_fail('', 1);
		}

		$this->_out('');
		$this->_out('--- Resposta da API ---');
		if (isset($resposta['score']))
		{
			$this->_out('score: ' . $resposta['score']);
		}
		if (isset($resposta['feedback']))
		{
			$this->_out('feedback: ' . (is_string($resposta['feedback']) ? $resposta['feedback'] : json_encode($resposta['feedback'])));
		}
		if ( ! isset($resposta['score']) && ! isset($resposta['feedback']))
		{
			$this->_out(json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
	}

	/**
	 * Lê CSV com colunas obrigatórias `municipio` e `populacao` e devolve linhas associativas.
	 *
	 * @param string $path Caminho absoluto do arquivo CSV de entrada.
	 * @return array<int, array<string, mixed>>
	 */
	protected function _read_csv($path)
	{
		if ( ! is_readable($path))
		{
			throw new RuntimeException('Arquivo não encontrado ou ilegível: ' . $path);
		}

		$h = fopen($path, 'rb');
		if ($h === FALSE)
		{
			throw new RuntimeException('Não foi possível abrir o CSV');
		}

		$header = fgetcsv($h);
		if ($header === FALSE)
		{
			fclose($h);
			throw new RuntimeException('CSV vazio');
		}

		$header = array_map('trim', $header);
		$map = array_flip($header);
		if ( ! isset($map['municipio'], $map['populacao']))
		{
			fclose($h);
			throw new RuntimeException('Colunas obrigatórias ausentes: municipio, populacao');
		}

		$rows = array();
		while (($row = fgetcsv($h)) !== FALSE)
		{
			$assoc = array();
			foreach ($header as $i => $key)
			{
				$assoc[$key] = isset($row[$i]) ? $row[$i] : '';
			}
			$rows[] = $assoc;
		}
		fclose($h);

		return $rows;
	}

	/**
	 * Grava o CSV de saída com colunas fixas de enriquecimento e status.
	 *
	 * @param string $path Caminho absoluto do arquivo de saída.
	 * @param array<int, array<string, mixed>> $linhas Linhas já enriquecidas pelo matcher.
	 * @return void
	 */
	protected function _write_result_csv($path, array $linhas)
	{
		$cols = array(
			'municipio_input',
			'populacao_input',
			'municipio_ibge',
			'uf',
			'regiao',
			'id_ibge',
			'status',
		);

		$h = fopen($path, 'wb');
		if ($h === FALSE)
		{
			throw new RuntimeException('Não foi possível gravar: ' . $path);
		}

		fputcsv($h, $cols);
		foreach ($linhas as $r)
		{
			$line = array();
			foreach ($cols as $c)
			{
				$line[] = isset($r[$c]) ? $r[$c] : '';
			}
			fputcsv($h, $line);
		}
		fclose($h);
	}

	/**
	 * Envia mensagem para stdout (CLI) ou HTML com quebra de linha.
	 *
	 * @param string $msg Texto a exibir.
	 * @return void
	 */
	protected function _out($msg)
	{
		echo $msg . (is_cli() ? PHP_EOL : "<br>\n");
	}

	/**
	 * Encerra o script com código de saída, opcionalmente após mensagem em stderr ou HTML.
	 *
	 * @param string $msg Mensagem de erro (pode ser string vazia se só for preciso sair com código).
	 * @param int $code Código de saída do processo (padrão 1).
	 * @return void
	 */
	protected function _fail($msg, $code = 1)
	{
		if ($msg !== '')
		{
			if (is_cli())
			{
				fwrite(STDERR, $msg . PHP_EOL);
			}
			else
			{
				echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
			}
		}
		exit($code);
	}
}
