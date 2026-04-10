<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Envio das estatísticas para a função Supabase.
 */
class ApiService {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * @param array<string, mixed> $stats
	 * @param string $token
	 * @return array<string, mixed>|null
	 */
	public function enviar($stats, $token)
	{
		$this->CI->load->config('ibge');
		$url = $this->CI->config->item('ibge_submit_url');

		$payload = json_encode(
			array('stats' => $stats),
			JSON_UNESCAPED_UNICODE
		);

		if ( ! function_exists('curl_init'))
		{
			throw new RuntimeException('cURL é necessário para enviar à API.');
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		));
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);

		$response = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === FALSE || $code >= 400)
		{
			throw new RuntimeException('Falha no envio à API (HTTP ' . $code . '): ' . (string) $response);
		}

		$decoded = json_decode($response, TRUE);

		return is_array($decoded) ? $decoded : NULL;
	}
}
