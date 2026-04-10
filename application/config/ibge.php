<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['ibge_submit_url'] = 'https://mynxlubykylncinttggu.functions.supabase.co/ibge-submit';

$config['supabase_email'] = getenv('SUPABASE_EMAIL') ?: '';
$config['supabase_password'] = getenv('SUPABASE_PASSWORD') ?: '';
$config['supabase_anon_key'] = getenv('SUPABASE_ANON_KEY') ?: '';

$_ibge_root = dirname(APPPATH);
$config['input_csv'] = getenv('INPUT_CSV') ?: ($_ibge_root . DIRECTORY_SEPARATOR . 'input.csv');
$config['output_csv'] = getenv('OUTPUT_CSV') ?: ($_ibge_root . DIRECTORY_SEPARATOR . 'resultado.csv');
unset($_ibge_root);
