<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Matching de nome de município contra mapa IBGE (exato + melhor similar_text).
 * Fuzzy: abaixo de 60% NAO_ENCONTRADO; 70% ou mais OK; entre 60% e 70% AMBIGUO.
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

		$melhor = NULL;
		$melhorScore = 0;

		foreach ($map as $key => $lista)
		{
			if ($key === 'erro' || ! is_array($lista))
			{
				continue;
			}

			similar_text($nome, (string) $key, $percent);

			if ($percent > $melhorScore)
			{
				$melhorScore = $percent;
				$melhor = isset($lista[0]) ? $lista[0] : NULL;
			}
		}

		if ($melhorScore < 60)
		{
			return array(
				'status' => 'NAO_ENCONTRADO',
				'data' => NULL,
			);
		}

		if ($melhorScore >= 70)
		{
			return array(
				'status' => 'OK',
				'data' => $melhor,
			);
		}

		return array(
			'status' => 'AMBIGUO',
			'data' => $melhor,
		);
	}
}
