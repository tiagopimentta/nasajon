<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Matching de nome de município contra mapa IBGE (exato + similar_text, status explícito).
 */
class MatcherService {

	/**
	 * @param string $texto
	 * @return string
	 */
	public function normalizar($texto)
	{
		$texto = strtolower((string) $texto);
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
		if ($converted !== FALSE)
		{
			$texto = $converted;
		}
		$texto = preg_replace('/[^a-z ]/', '', $texto);

		return trim($texto);
	}

	/**
	 * @param string $nome
	 * @param array<string, mixed> $map Mapa de municípios ou array com chave 'erro' (tratado no processador).
	 * @return array{status: string, data: mixed}
	 */
	public function buscar($nome, $map)
	{
		$nome = $this->normalizar($nome);

		if ($nome === '')
		{
			return array(
				'status' => 'NAO_ENCONTRADO',
				'data' => NULL,
			);
		}

		if (isset($map[$nome]) && is_array($map[$nome]))
		{
			if (count($map[$nome]) > 1)
			{
				return array(
					'status' => 'AMBIGUO',
					'data' => $map[$nome],
				);
			}
			if (count($map[$nome]) === 1)
			{
				return array(
					'status' => 'OK',
					'data' => $map[$nome][0],
				);
			}
		}

		$candidatos = array();

		foreach ($map as $key => $lista)
		{
			if ($key === 'erro' || ! is_array($lista))
			{
				continue;
			}

			similar_text($nome, (string) $key, $percent);

			if ($percent > 80)
			{
				foreach ($lista as $item)
				{
					$candidatos[] = $item;
				}
			}
		}

		if (count($candidatos) === 1)
		{
			return array(
				'status' => 'OK',
				'data' => $candidatos[0],
			);
		}

		if (count($candidatos) > 1)
		{
			return array(
				'status' => 'AMBIGUO',
				'data' => $candidatos,
			);
		}

		return array(
			'status' => 'NAO_ENCONTRADO',
			'data' => NULL,
		);
	}
}
