<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Leitura de CSV de entrada (municipio, populacao).
 */
class CsvService {

	/**
	 * @param string $file Caminho absoluto do arquivo
	 * @return array<int, array{municipio: string, populacao: int}>
	 */
	public function ler($file)
	{
		if ( ! is_readable($file))
		{
			throw new RuntimeException('CSV não encontrado ou ilegível: ' . $file);
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES);
		if ($lines === FALSE)
		{
			throw new RuntimeException('Não foi possível ler o CSV.');
		}

		$rows = array();
		foreach ($lines as $line)
		{
			if ($line !== '' && $line !== "\xEF\xBB\xBF")
			{
				$rows[] = str_getcsv($line);
			}
		}

		if (count($rows) < 1)
		{
			throw new RuntimeException('CSV vazio.');
		}

		$header = array_map('trim', array_shift($rows));
		$map = array_flip($header);
		if ( ! isset($map['municipio'], $map['populacao']))
		{
			throw new RuntimeException('CSV deve conter colunas: municipio, populacao');
		}

		$data = array();

		foreach ($rows as $row)
		{
			$m = isset($row[$map['municipio']]) ? trim((string) $row[$map['municipio']]) : '';
			$p = isset($row[$map['populacao']]) ? $row[$map['populacao']] : '0';
			$data[] = array(
				'municipio' => $m,
				'populacao' => (int) $p,
			);
		}

		return $data;
	}
}
