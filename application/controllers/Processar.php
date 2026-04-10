<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Processar extends CI_Controller {

	/**
	 * Carrega a configuração `ibge` (URLs e variáveis de ambiente).
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->config('ibge');
	}

	/**
	 * Executa o pipeline final: mapa IBGE (cache), leitura do CSV, matching, estatísticas,
	 * gravação de `resultado.csv`, login Supabase e envio do payload à função remota.
	 * Recomendado via CLI: `php index.php processar run`.
	 * @return void
	 */
	public function run()
	{
		$email = trim((string) $this->config->item('supabase_email'));
		$password = (string) $this->config->item('supabase_password');
		$apikey = trim((string) $this->config->item('supabase_anon_key'));

		if ($email === '' || $password === '' || $apikey === '')
		{
			$this->_out_err('Defina SUPABASE_EMAIL, SUPABASE_PASSWORD e SUPABASE_ANON_KEY.');
		}

		$this->load->library('IbgeService');
		$this->load->library('CsvService');
		$this->load->library('MatcherService');
		$this->load->library('ProcessadorService');
		$this->load->library('StatsService');
		$this->load->library('ApiService');
		$this->load->library('Supabase_auth');
		$this->load->helper('csv');

		$csvPath = APPPATH . 'data' . DIRECTORY_SEPARATOR . 'input.csv';
		if ( ! is_readable($csvPath))
		{
			$csvPath = $this->config->item('input_csv');
		}

		$this->_out('Carregando mapa IBGE (cache)...');
		$map = $this->ibgeservice->getMunicipiosMap();

		$this->_out('Lendo CSV: ' . $csvPath);
		$csv = $this->csvservice->ler($csvPath);

		$this->_out('Processando matching...');
		list($resultado, $stats) = $this->processadorservice->processar(
			$csv,
			$map,
			$this->matcherservice
		);

		$statsFinal = $this->statsservice->calcular($stats);

		$outputPath = $this->config->item('output_csv');
		$this->_out('Gerando ' . $outputPath);
		gerar_csv($resultado, $outputPath);

		$this->_out('Autenticando no Supabase...');
		try
		{
			$token = $this->supabase_auth->login($email, $password, $apikey);
		}
		catch (Exception $e)
		{
			$this->_out_err('Falha no login: ' . $e->getMessage());
		}

		$payload = array(
			'total_municipios' => $statsFinal['total_municipios'],
			'total_ok' => $statsFinal['total_ok'],
			'total_nao_encontrado' => $statsFinal['total_nao_encontrado'],
			'total_erro_api' => $statsFinal['total_erro_api'],
			'pop_total_ok' => $statsFinal['pop_total_ok'],
			'medias_por_regiao' => $statsFinal['medias_por_regiao'],
		);

		$this->_out('Enviando estatísticas à API...');
		try
		{
			$resposta = $this->apiservice->enviar($payload, $token);
		}
		catch (Exception $e)
		{
			$this->_out_err('Falha no envio: ' . $e->getMessage());
		}

		$this->_out('');
		$this->_out('--- Resposta da API ---');
		if (is_cli())
		{
			print_r($resposta);
		}
		else
		{
			echo '<pre>';
			print_r($resposta);
			echo '</pre>';
		}
	}

	/**
	 * Saída padrão: linha em CLI ou texto escapado em HTML.
	 * @param string $msg Texto a exibir.
	 * @return void
	 */
	protected function _out($msg)
	{
		if (is_cli())
		{
			echo $msg . PHP_EOL;
		}
		else
		{
			echo htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
		}
	}

	/**
	 * Mensagem de erro em stderr (CLI) ou HTML; encerra o processo com código 1.
	 * @param string $msg Texto do erro.
	 * @return void
	 */
	protected function _out_err($msg)
	{
		if (is_cli())
		{
			fwrite(STDERR, $msg . PHP_EOL);
		}
		else
		{
			echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
		}
		exit(1);
	}
}
