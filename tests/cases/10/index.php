<?php declare(strict_types = 1);

$a = new B($_GET);

// tainted
echo $a->getSource('x');

// ok
echo (int) $a->getSource('y');
