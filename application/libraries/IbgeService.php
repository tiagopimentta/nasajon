<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Serviço IBGE: busca municípios com cache em arquivo e mapa por nome normalizado.
 */
class IbgeService {

	private $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

	/** @var string */
	private $cache_file;

	public function __construct()
	{
		$this->cache_file = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'ibge.json';
	}

	/**
	 * Lista bruta de municípios (JSON do IBGE decodificado).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getMunicipios()
	{
		$this->_ensure_cache_dir();

		if (file_exists($this->cache_file))
		{
			$json = file_get_contents($this->cache_file);
		}
		else
		{
			$json = $this->_http_get($this->url);
			file_put_contents($this->cache_file, $json);
		}

		$data = json_decode($json, TRUE);
		if ( ! is_array($data))
		{
			throw new RuntimeException('Resposta IBGE inválida ou JSON corrompido no cache.');
		}

		return $data;
	}

	/**
	 * Mapa indexado por nome normalizado; vários municípios com mesmo nome normalizado viram lista.
	 *
	 * @return array<string, array<int, array{nome: string, uf: string, regiao: string, id: mixed}>>
	 */
	public function getMunicipiosMap()
	{
		$data = $this->getMunicipios();
		$map = array();

		foreach ($data as $item)
		{
			if ( ! isset($item['nome']))
			{
				continue;
			}

			$nome = $this->normalizar($item['nome']);

			$uf = '';
			$regiao = '';
			if (isset($item['microrregiao']['mesorregiao']['UF']))
			{
				$uf = isset($item['microrregiao']['mesorregiao']['UF']['sigla'])
					? $item['microrregiao']['mesorregiao']['UF']['sigla']
					: '';
				if (isset($item['microrregiao']['mesorregiao']['UF']['regiao']['nome']))
				{
					$regiao = $item['microrregiao']['mesorregiao']['UF']['regiao']['nome'];
				}
			}

			if ( ! isset($map[$nome]))
			{
				$map[$nome] = array();
			}

			$map[$nome][] = array(
				'nome' => $item['nome'],
				'uf' => $uf,
				'regiao' => $regiao,
				'id' => isset($item['id']) ? $item['id'] : NULL,
			);
		}

		return $map;
	}

	/**
	 * @param string $texto
	 * @return string
	 */
	private function normalizar($texto)
	{
		$texto = strtolower($texto);
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
		if ($converted !== FALSE)
		{
			$texto = $converted;
		}
		$texto = preg_replace('/[^a-z ]/', '', $texto);

		return trim($texto);
	}

	private function _ensure_cache_dir()
	{
		$dir = dirname($this->cache_file);
		if ( ! is_dir($dir))
		{
			@mkdir($dir, 0777, TRUE);
		}
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private function _http_get($url)
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
				throw new RuntimeException('Falha ao baixar municípios do IBGE (HTTP ' . $code . ').');
			}

			return $body;
		}

		$ctx = stream_context_create(array(
			'http' => array('timeout' => 120),
		));
		$body = @file_get_contents($url, FALSE, $ctx);
		if ($body === FALSE)
		{
			throw new RuntimeException('Falha ao baixar municípios do IBGE (file_get_contents).');
		}

		return $body;
	}
}
