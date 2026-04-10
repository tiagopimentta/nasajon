<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API de localidades do IBGE.
 */
class Ibge_client {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fetch_municipios()
	{
		$url = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';
		$json = $this->_http_get($url);
		$data = json_decode($json, TRUE);
		if ( ! is_array($data))
		{
			throw new RuntimeException('Resposta IBGE inesperada (não é lista)');
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $m
	 * @return array<string, mixed>
	 */
	public function flatten_municipio($m)
	{
		$mic = isset($m['microrregiao']) ? $m['microrregiao'] : array();
		$meso = isset($mic['mesorregiao']) ? $mic['mesorregiao'] : array();
		$uf = isset($meso['UF']) ? $meso['UF'] : array();
		$sigla = isset($uf['sigla']) ? $uf['sigla'] : '';
		$regiao = '';
		if (isset($uf['regiao']) && is_array($uf['regiao']))
		{
			$regiao = isset($uf['regiao']['nome']) ? $uf['regiao']['nome'] : '';
		}

		return array(
			'id' => $m['id'],
			'nome' => isset($m['nome']) ? $m['nome'] : '',
			'uf' => $sigla,
			'regiao' => $regiao,
		);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	protected function _http_get($url)
	{
		if (function_exists('curl_init'))
		{
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_TIMEOUT => 120,
			));
			$body = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($body === FALSE || $code >= 400)
			{
				throw new RuntimeException('HTTP IBGE falhou: ' . $code);
			}

			return $body;
		}

		$ctx = stream_context_create(array(
			'http' => array('timeout' => 120),
		));
		$body = @file_get_contents($url, FALSE, $ctx);
		if ($body === FALSE)
		{
			throw new RuntimeException('Falha ao buscar IBGE (file_get_contents)');
		}

		return $body;
	}
}
