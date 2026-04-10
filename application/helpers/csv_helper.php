<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Colunas e ordem fixas do resultado (conforme prova).
 */
if ( ! defined('CSV_RESULTADO_COLUNAS'))
{
	define('CSV_RESULTADO_COLUNAS', array(
		'municipio_input',
		'populacao_input',
		'municipio_ibge',
		'uf',
		'regiao',
		'id_ibge',
		'status',
	));
}

/**
 * Gera CSV de resultado na raiz do projeto (FCPATH) com colunas na ordem exigida.
 *
 * @param array<int, array<string, mixed>> $data
 * @param string|null $path Caminho completo; padrão FCPATH resultado.csv
 */
function gerar_csv($data, $path = NULL)
{
	if (count($data) < 1)
	{
		return;
	}

	if ($path === NULL)
	{
		$path = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resultado.csv';
	}

	$file = fopen($path, 'wb');
	if ($file === FALSE)
	{
		throw new RuntimeException('Não foi possível criar o arquivo CSV de saída.');
	}

	$cols = CSV_RESULTADO_COLUNAS;
	fputcsv($file, $cols);

	foreach ($data as $row)
	{
		$line = array();
		foreach ($cols as $c)
		{
			$line[] = isset($row[$c]) ? $row[$c] : '';
		}
		fputcsv($file, $line);
	}

	fclose($file);
}
