<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Match exato + similar_text: melhor candidato; faixas 60% / 70% (ratio 0,60 / 0,70).
 */
class Municipio_matcher {

	/** Similaridade mínima (0–1) para OK (equivale a 70% em similar_text). */
	const DEFAULT_CUTOFF = 0.70;
	/** Abaixo disso: NAO_ENCONTRADO; entre este e DEFAULT_CUTOFF: AMBIGUO. */
	const WEAK_CUTOFF = 0.60;
	const OK = 'OK';
	const NAO_ENCONTRADO = 'NAO_ENCONTRADO';
	const AMBIGUO = 'AMBIGUO';

	/** @var Text_normalizer */
	protected $normalizer;

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('Text_normalizer');
		$this->normalizer = $this->CI->text_normalizer;
	}

	/**
	 * @param array<int, array<string, mixed>> $ibge_flat
	 * @return array<int, array<string, mixed>>
	 */
	protected function rows_com_nome_norm(array $ibge_flat)
	{
		$out = array();
		foreach ($ibge_flat as $r)
		{
			$nome = isset($r['nome']) ? $r['nome'] : '';
			$row = $r;
			$row['_nome_norm'] = $this->normalizer->normalize($nome);
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	protected function strip_internal($row)
	{
		unset($row['_nome_norm']);

		return $row;
	}

	/**
	 * @param string $municipio_input
	 * @param array<int, array<string, mixed>> $ibge_rows_norm
	 * @return array{0: ?array<string, mixed>, 1: string}
	 */
	public function match_municipio($municipio_input, array $ibge_rows_norm)
	{
		$q = $this->normalizer->normalize($municipio_input);
		if ($q === '')
		{
			return array(NULL, self::NAO_ENCONTRADO);
		}

		$exatos = array();
		foreach ($ibge_rows_norm as $r)
		{
			if (isset($r['_nome_norm']) && $r['_nome_norm'] === $q)
			{
				$exatos[] = $r;
			}
		}

		if (count($exatos) === 1)
		{
			return array($this->strip_internal($exatos[0]), self::OK);
		}
		if (count($exatos) > 1)
		{
			return array(NULL, self::AMBIGUO);
		}

		$unicos_map = array();
		foreach ($ibge_rows_norm as $r)
		{
			if ( ! empty($r['_nome_norm']))
			{
				$unicos_map[$r['_nome_norm']] = TRUE;
			}
		}
		$unicos = array_keys($unicos_map);
		sort($unicos, SORT_STRING);

		if (count($unicos) === 0)
		{
			return array(NULL, self::NAO_ENCONTRADO);
		}

		$bestScore = 0.0;
		$bestNorm = '';
		foreach ($unicos as $p)
		{
			similar_text($q, $p, $pct);
			$ratio = $pct / 100.0;
			if ($ratio > $bestScore)
			{
				$bestScore = $ratio;
				$bestNorm = $p;
			}
		}

		if ($bestScore < self::WEAK_CUTOFF)
		{
			return array(NULL, self::NAO_ENCONTRADO);
		}

		$candidatos = array();
		foreach ($ibge_rows_norm as $r)
		{
			if (isset($r['_nome_norm']) && $r['_nome_norm'] === $bestNorm)
			{
				$candidatos[] = $r;
			}
		}

		if (count($candidatos) === 0)
		{
			return array(NULL, self::NAO_ENCONTRADO);
		}
		if (count($candidatos) > 1)
		{
			return array(NULL, self::AMBIGUO);
		}

		if ($bestScore >= self::DEFAULT_CUTOFF)
		{
			return array($this->strip_internal($candidatos[0]), self::OK);
		}

		return array(NULL, self::AMBIGUO);
	}

	/**
	 * @param array<int, array<string, mixed>> $registros
	 * @param array<int, array<string, mixed>> $ibge_flat
	 * @return array<int, array<string, mixed>>
	 */
	public function enrich_records(array $registros, array $ibge_flat)
	{
		$ibge_rows_norm = $this->rows_com_nome_norm($ibge_flat);
		$saida = array();

		foreach ($registros as $rec)
		{
			$mun = isset($rec['municipio']) ? $rec['municipio'] : '';
			$pop = isset($rec['populacao']) ? $rec['populacao'] : NULL;
			list($matched, $status) = $this->match_municipio((string) $mun, $ibge_rows_norm);

			$saida[] = array(
				'municipio_input' => $mun,
				'populacao_input' => $pop,
				'municipio_ibge' => $matched ? $matched['nome'] : '',
				'uf' => $matched ? $matched['uf'] : '',
				'regiao' => $matched ? $matched['regiao'] : '',
				'id_ibge' => $matched ? $matched['id'] : '',
				'status' => $status,
			);
		}

		return $saida;
	}
}
