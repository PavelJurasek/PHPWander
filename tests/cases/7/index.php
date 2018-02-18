<?php declare(strict_types = 1);

$items = [
	3,
	$_GET['b'],
	'str',
	4 => 1.4,
];

foreach ($items as $param) {
	echo $param;
}
