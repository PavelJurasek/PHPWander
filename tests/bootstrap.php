<?php declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('Tester\Assert')) {
	echo "Install Nette Tester using `composer update --dev`\n";
	exit(1);
}

\Tester\Environment::setup();

$tmpDir = __DIR__ . '/../tmp';
$cwd = getcwd();

$containerFactory = new \PHPWander\DI\ContainerFactory($cwd);

if (!isset($tmpDir)) {
	$tmpDir = sys_get_temp_dir() . '/phpwander';
}

$additionalConfigFiles = [];

return $containerFactory->create($tmpDir, $additionalConfigFiles);
