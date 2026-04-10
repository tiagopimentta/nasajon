<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Estatísticas agregadas (plano) e payload para a API.
 */
class Ibge_stats {

	/**
	 * @param array<int, array<string, mixed>> $enriched_rows
	 * @param int $api_error_count
	 * @return array<string, mixed>
	 */
	public function compute_stats(array $enriched_rows, $api_error_count = 0)
	{
		$total = count($enriched_rows);
		$total_ok = 0;
		$total_nao_encontrado = 0;
		$total_ambiguo = 0;

		foreach ($enriched_rows as $r)
		{
			$st = isset($r['status']) ? $r['status'] : '';
			if ($st === 'OK')
			{
				$total_ok++;
			}
			elseif ($st === 'NAO_ENCONTRADO')
			{
				$total_nao_encontrado++;
			}
			elseif ($st === 'AMBIGUO')
			{
				$total_ambiguo++;
			}
		}

		$pop_total_ok = 0;
		$somas_regiao = array();
		$contagem_regiao = array();

		foreach ($enriched_rows as $r)
		{
			if ( ! isset($r['status']) || $r['status'] !== 'OK')
			{
				continue;
			}

			$pop = isset($r['populacao_input']) ? $r['populacao_input'] : NULL;
			$pop_f = 0.0;
			if ($pop !== NULL && trim((string) $pop) !== '')
			{
				$pop_f = (float) $pop;
			}
			$pop_total_ok += (int) $pop_f;

			$reg = isset($r['regiao']) ? $r['regiao'] : '';
			if ($reg === '')
			{
				continue;
			}
			if ( ! isset($somas_regiao[$reg]))
			{
				$somas_regiao[$reg] = 0.0;
				$contagem_regiao[$reg] = 0;
			}
			$somas_regiao[$reg] += $pop_f;
			$contagem_regiao[$reg]++;
		}

		$medias_por_regiao = array();
		foreach ($somas_regiao as $reg => $soma)
		{
			$n = isset($contagem_regiao[$reg]) ? (int) $contagem_regiao[$reg] : 0;
			if ($n > 0)
			{
				$medias_por_regiao[$reg] = round($soma / $n, 4);
			}
		}

		return array(
			'total_municipios' => $total,
			'total_ok' => $total_ok,
			'total_nao_encontrado' => $total_nao_encontrado,
			'total_ambiguo' => $total_ambiguo,
			'total_erro_api' => $api_error_count,
			'pop_total_ok' => (int) $pop_total_ok,
			'medias_por_regiao' => $medias_por_regiao,
		);
	}

	/**
	 * @param array<string, mixed> $full
	 * @return array<string, mixed>
	 */
	public function stats_for_api_payload(array $full)
	{
		$keys = array(
			'total_municipios',
			'total_ok',
			'total_nao_encontrado',
			'total_erro_api',
			'pop_total_ok',
			'medias_por_regiao',
		);
		$out = array();
		foreach ($keys as $k)
		{
			$out[$k] = isset($full[$k]) ? $full[$k] : NULL;
		}

		return $out;
	}
}
