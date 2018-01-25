<?php declare(strict_types = 1);

$a = ($b = 4) + 5;
function id($s) {
	return $s;
}

$c = file_get_contents(__DIR__ . '/' . id($_GET['f']));

$d = file_get_contents(__DIR__ . '/' . basename($_GET['f']));

$d = file_get_contents(__DIR__ . '/' . id($_GET['f']));

// tainted
print_r($c);
