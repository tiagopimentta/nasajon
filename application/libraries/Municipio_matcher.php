<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Match exato + similaridade (equivalente a difflib.get_close_matches).
 */
class Municipio_matcher {

	const DEFAULT_CUTOFF = 0.82;
	const CLOSE_N = 5;
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
	 * @param string $word
	 * @param array<int, string> $possibilities
	 * @param int $n
	 * @param float $cutoff
	 * @return array<int, string>
	 */
	protected function get_close_matches($word, array $possibilities, $n = 5, $cutoff = 0.82)
	{
		$scored = array();
		foreach ($possibilities as $p)
		{
			if ($p === '')
			{
				continue;
			}
			similar_text($word, $p, $pct);
			$ratio = $pct / 100.0;
			if ($ratio >= $cutoff)
			{
				$scored[] = array('s' => $p, 'r' => $ratio);
			}
		}

		usort($scored, function ($a, $b) {
			if ($a['r'] == $b['r'])
			{
				return strcmp($a['s'], $b['s']);
			}

			return ($a['r'] < $b['r']) ? 1 : -1;
		});

		$out = array();
		$seen = array();
		foreach ($scored as $item)
		{
			if (isset($seen[$item['s']]))
			{
				continue;
			}
			$seen[$item['s']] = TRUE;
			$out[] = $item['s'];
			if (count($out) >= $n)
			{
				break;
			}
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
	 * @param float $cutoff
	 * @return array{0: ?array<string, mixed>, 1: string}
	 */
	public function match_municipio($municipio_input, array $ibge_rows_norm, $cutoff = self::DEFAULT_CUTOFF)
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

		$close = $this->get_close_matches($q, $unicos, self::CLOSE_N, $cutoff);
		if (count($close) === 0)
		{
			return array(NULL, self::NAO_ENCONTRADO);
		}
		if (count($close) > 1)
		{
			return array(NULL, self::AMBIGUO);
		}

		$only = $close[0];
		$candidatos = array();
		foreach ($ibge_rows_norm as $r)
		{
			if (isset($r['_nome_norm']) && $r['_nome_norm'] === $only)
			{
				$candidatos[] = $r;
			}
		}

		if (count($candidatos) === 1)
		{
			return array($this->strip_internal($candidatos[0]), self::OK);
		}
		if (count($candidatos) > 1)
		{
			return array(NULL, self::AMBIGUO);
		}

		return array(NULL, self::NAO_ENCONTRADO);
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
