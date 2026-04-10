<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Normalização para matching: transliteração, trim, minúsculas.
 */
class Text_normalizer {

	/**
	 * @param string|null $text
	 * @return string
	 */
	public function normalize($text)
	{
		if ($text === null || $text === '')
		{
			return '';
		}

		$s = trim((string) $text);

		if (function_exists('transliterator_transliterate'))
		{
			$out = @transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
			if (is_string($out) && $out !== '')
			{
				$s = $out;
			}
		}
		else
		{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
			if ($converted !== FALSE)
			{
				$s = $converted;
			}
		}

		return mb_strtolower($s, 'UTF-8');
	}
}
