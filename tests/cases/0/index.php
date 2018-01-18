<?php declare(strict_types = 1);

$a = ($b = 4) + 5;

$c = file_get_contents(__FILE__);

// tainted
print_r($c);
