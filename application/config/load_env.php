<?php
/**
 * @param string $path Caminho absoluto do arquivo .env
 * @return void
 */
function nasajon_load_dotenv($path)
{
	if ( ! is_string($path) || $path === '' || ! is_readable($path))
	{
		return;
	}

	$lines = @file($path, FILE_IGNORE_NEW_LINES);
	if ($lines === FALSE)
	{
		return;
	}

	foreach ($lines as $line)
	{
		$line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
		$line = trim($line);

		if ($line === '' || $line[0] === '#')
		{
			continue;
		}

		if (strpos($line, '=') === FALSE)
		{
			continue;
		}

		if (preg_match('/^export\s+/i', $line))
		{
			$line = preg_replace('/^export\s+/i', '', $line);
		}

		list($name, $value) = explode('=', $line, 2);
		$name = trim($name);
		$value = trim($value);

		if ($name === '')
		{
			continue;
		}

		$len = strlen($value);
		if ($len >= 2)
		{
			$q0 = $value[0];
			$q1 = $value[$len - 1];
			if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'"))
			{
				$value = substr($value, 1, -1);
			}
		}

		putenv($name . '=' . $value);
		$_ENV[$name] = $value;
		$_SERVER[$name] = $value;
	}
}
