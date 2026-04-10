<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ibge_client {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * Busca e retorna a lista bruta de municípios do IBGE (JSON decodificado).
	 * @return array<int, array<string, mixed>>
	 */
	public function fetch_municipios()
	{
		$this->CI->load->library('IbgeService');

		return $this->CI->ibgeservice->getMunicipios();
	}

	/**
	 * Normaliza um município para um array simples com id, nome, UF e região.
	 * @param array<string, mixed> $m Município IBGE com estrutura aninhada.
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
}
