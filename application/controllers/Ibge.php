<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Testes de integração com a API de localidades do IBGE (cache em arquivo + mapa normalizado).
 */
class Ibge extends CI_Controller {

	/**
	 * Exibe uma amostra do mapa de municípios (primeiras chaves) para validar cache e formato.
	 *
	 * @return void
	 */
	public function index()
	{
		$this->load->library('IbgeService');

		$map = $this->ibgeservice->getMunicipiosMap();

		$this->output->set_content_type('text/html', 'UTF-8');
		echo '<pre>';
		print_r(array_slice($map, 0, 5, TRUE));
	}
}
