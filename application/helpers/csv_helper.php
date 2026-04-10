<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fixed column order for the output CSV (grading / Excel).
 */
if ( ! defined('CSV_RESULT_COLUMNS'))
{
	define('CSV_RESULT_COLUMNS', array(
		'municipio_input',
		'populacao_input',
		'municipio_ibge',
		'uf',
		'regiao',
		'id_ibge',
		'status',
	));
}

/** @var string Column delimiter (unquoted CSV, one line per row) */
if ( ! defined('CSV_DELIMITER'))
{
	define('CSV_DELIMITER', ',');
}

/**
 * Builds one row in fixed column order (7 fields) for fputcsv.
 *
 * @param array<string, mixed> $row
 * @return array<int, string>
 */
function build_ordered_csv_row($row)
{
	$out = array();
	foreach (CSV_RESULT_COLUMNS as $col)
	{
		if ( ! array_key_exists($col, $row))
		{
			$out[] = '';
			continue;
		}
		$v = $row[$col];
		if ($v === NULL)
		{
			$out[] = '';
		}
		elseif (is_bool($v))
		{
			$out[] = $v ? '1' : '';
		}
		else
		{
			$out[] = (string) $v;
		}
	}

	return $out;
}

/**
 * Writes one CSV row without field quotes (comma-separated).
 * PHP fputcsv quotes values that contain spaces; this matches a plain "a,b,c" file.
 * Line breaks inside values are stripped so one physical line = one row.
 *
 * @param resource $file
 * @param array<int, string|int|float> $fields
 */
function write_csv_line_unquoted($file, array $fields)
{
	$parts = array();
	foreach (array_map('strval', $fields) as $v)
	{
		$parts[] = str_replace(array("\r", "\n"), '', $v);
	}

	fwrite($file, implode(CSV_DELIMITER, $parts) . "\n");
}

/**
 * Writes resultado.csv: UTF-8 BOM, fixed header, comma-separated rows without quotes.
 *
 * @param array<int, array<string, mixed>> $data
 * @param string|null $path Absolute path; default FCPATH/resultado.csv
 */
function write_clean_csv($data, $path = NULL)
{
	if ($path === NULL)
	{
		$path = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resultado.csv';
	}

	$file = fopen($path, 'wb');
	if ($file === FALSE)
	{
		throw new RuntimeException('Could not create CSV file: ' . $path);
	}

	fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

	write_csv_line_unquoted($file, CSV_RESULT_COLUMNS);

	foreach ($data as $row)
	{
		write_csv_line_unquoted($file, build_ordered_csv_row($row));
	}

	fclose($file);
}

/**
 * Backwards-compatible alias for write_clean_csv().
 *
 * @param array<int, array<string, mixed>> $data
 * @param string|null $path
 */
function write_csv($data, $path = NULL)
{
	write_clean_csv($data, $path);
}
