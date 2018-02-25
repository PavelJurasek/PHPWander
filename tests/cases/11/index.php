<?php declare(strict_types = 1);

$a = empty($_GET['a']) ? 'abc' : $_GET['a'];

//if (empty($_GET['a'])) {
//	$a = 'abc';
//} else {
//	$a = $_GET['a'];
//}

echo $a;
