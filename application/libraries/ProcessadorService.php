<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cruza linhas do CSV com o mapa IBGE via MatcherService (status OK, NAO_ENCONTRADO, AMBIGUO, ERRO_API).
 */
class ProcessadorService {

	/**
	 * @param array<int, array{municipio: string, populacao: int}> $csvData
	 * @param array<string, mixed> $map Mapa IBGE ou array('erro' => true)
	 * @param MatcherService $matcher
	 * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
	 */
	public function processar($csvData, $map, $matcher)
	{
		$resultado = array();

		$stats = array(
			'total_municipios' => 0,
			'total_ok' => 0,
			'total_nao_encontrado' => 0,
			'total_ambiguo' => 0,
			'total_erro_api' => 0,
			'pop_total_ok' => 0,
			'regioes' => array(),
		);

		if (isset($map['erro']) && $map['erro'])
		{
			foreach ($csvData as $row)
			{
				$resultado[] = $this->_linha_resultado($row, 'ERRO_API', NULL);
				$stats['total_erro_api']++;
				$stats['total_municipios']++;
			}

			return array($resultado, $stats);
		}

		foreach ($csvData as $row)
		{
			$matchResult = $matcher->buscar($row['municipio'], $map);

			$status = $matchResult['status'];
			$match = $matchResult['data'];

			if ($status === 'OK')
			{
				$stats['total_ok']++;
				$stats['pop_total_ok'] += $row['populacao'];

				$regiao = is_array($match) && isset($match['regiao']) ? $match['regiao'] : '';

				if ($regiao !== '')
				{
					if ( ! isset($stats['regioes'][$regiao]))
					{
						$stats['regioes'][$regiao] = array();
					}
					$stats['regioes'][$regiao][] = $row['populacao'];
				}
			}
			elseif ($status === 'NAO_ENCONTRADO')
			{
				$stats['total_nao_encontrado']++;
			}
			elseif ($status === 'AMBIGUO')
			{
				$stats['total_ambiguo']++;
			}

			$linhaMatch = NULL;
			if ($status === 'OK' && is_array($match))
			{
				$linhaMatch = $match;
			}

			$resultado[] = $this->_linha_resultado($row, $status, $linhaMatch);

			$stats['total_municipios']++;
		}

		return array($resultado, $stats);
	}

	/**
	 * @param array{municipio: string, populacao: int} $row
	 * @param string $status
	 * @param array<string, mixed>|null $match Registro IBGE único ou null
	 * @return array<string, mixed>
	 */
	protected function _linha_resultado($row, $status, $match)
	{
		return array(
			'municipio_input' => $row['municipio'],
			'populacao_input' => $row['populacao'],
			'municipio_ibge' => ($match && isset($match['nome'])) ? $match['nome'] : '',
			'uf' => ($match && isset($match['uf'])) ? $match['uf'] : '',
			'regiao' => ($match && isset($match['regiao'])) ? $match['regiao'] : '',
			'id_ibge' => ($match && array_key_exists('id', $match)) ? $match['id'] : '',
			'status' => $status,
		);
	}
}
