<?php declare(strict_types = 1);

// tainted result
$user = require_once __DIR__ . '/file.php';

// ok
if ($bool = array_key_exists('file', $_GET)) {
	//  tainted
	require_once __DIR__ . '/' . $_GET['file'] . '.php';
}

// tainted
echo $user['id'];
