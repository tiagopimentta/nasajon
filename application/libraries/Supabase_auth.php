<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Login Supabase (password grant) e envio do payload de stats.
 */
class Supabase_auth {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * @param string $email
	 * @param string $password
	 * @param string $apikey
	 * @return string access_token
	 */
	public function login($email, $password, $apikey)
	{
		$url = 'https://mynxlubykylncinttggu.supabase.co/auth/v1/token?grant_type=password';
		$payload = json_encode(array(
			'email' => $email,
			'password' => $password,
		));

		$body = $this->_http_post($url, $payload, array(
			'apikey: ' . $apikey,
			'Content-Type: application/json',
		), 60);

		$data = json_decode($body, TRUE);
		if (empty($data['access_token']))
		{
			throw new RuntimeException('Resposta de login sem access_token');
		}

		return $data['access_token'];
	}

	/**
	 * @param string $token
	 * @param string $submit_url
	 * @param array<string, mixed> $stats_payload
	 * @return array<string, mixed>
	 */
	public function submit_stats($token, $submit_url, array $stats_payload)
	{
		$json = json_encode(array('stats' => $stats_payload));
		$body = $this->_http_post($submit_url, $json, array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		), 120);

		$decoded = json_decode($body, TRUE);
		if (is_array($decoded))
		{
			return $decoded;
		}

		return array('raw' => $body);
	}

	/**
	 * @param string $url
	 * @param string $body
	 * @param array<int, string> $headers
	 * @param int $timeout
	 * @return string
	 */
	protected function _http_post($url, $body, array $headers, $timeout)
	{
		if (function_exists('curl_init'))
		{
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_POST => TRUE,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_TIMEOUT => $timeout,
			));
			$resp = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($resp === FALSE || $code >= 400)
			{
				throw new RuntimeException('HTTP POST falhou: ' . $code . ' ' . (string) $resp);
			}

			return $resp;
		}

		throw new RuntimeException('cURL é necessário para chamadas HTTP');
	}
}
