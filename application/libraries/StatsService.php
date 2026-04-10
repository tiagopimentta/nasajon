<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Calcula médias por região (apenas linhas OK) e garante campos do payload de estatísticas.
 */
class StatsService {

	/** @var array<int, string> */
	protected $campos_obrigatorios = array(
		'total_municipios',
		'total_ok',
		'total_nao_encontrado',
		'total_ambiguo',
		'total_erro_api',
		'pop_total_ok',
		'medias_por_regiao',
	);

	/**
	 * @param array<string, mixed> $stats
	 * @return array<string, mixed>
	 */
	public function calcular($stats)
	{
		$medias = array();

		if (isset($stats['regioes']) && is_array($stats['regioes']))
		{
			foreach ($stats['regioes'] as $regiao => $valores)
			{
				if ( ! is_array($valores) || count($valores) === 0)
				{
					continue;
				}
				$medias[$regiao] = round(array_sum($valores) / count($valores), 4);
			}
		}

		$stats['medias_por_regiao'] = $medias;

		unset($stats['regioes']);

		foreach ($this->campos_obrigatorios as $campo)
		{
			if ( ! array_key_exists($campo, $stats))
			{
				if ($campo === 'medias_por_regiao')
				{
					$stats['medias_por_regiao'] = array();
				}
				else
				{
					$stats[$campo] = 0;
				}
			}
		}

		return $stats;
	}
}
