<?php declare(strict_types = 1);

$a = new A($_GET);

// tainted
echo $a->getSource('a');

// ok
echo (int) $a->getSource('a');
