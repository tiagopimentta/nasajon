<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller padrão do esqueleto CodeIgniter (página de boas-vindas).
 *
 * @see https://codeigniter.com/userguide3/general/urls.html
 */
class Welcome extends CI_Controller {

	/**
	 * Carrega a view `welcome_message`.
	 *
	 * Rota típica: `index.php/welcome` ou `index.php/welcome/index`.
	 *
	 * @return void
	 */
	public function index()
	{
		$this->load->view('welcome_message');
	}
}
