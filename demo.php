<?php declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

Tracy\Debugger::enable();

function taint($variables) {
	$result = array_map(function ($taint) {
		return [
			\PHPWander\Taint::UNKNOWN => 'unknown',
			\PHPWander\Taint::TAINTED => 'tainted',
			\PHPWander\Taint::UNTAINTED => 'untainted',
			\PHPWander\Taint::BOTH => 'both',
		][$taint];
	}, $variables);
	dump($result);
}

$tmpDir = __DIR__ . '/tmp';
$cwd = getcwd();

$containerFactory = new \PHPWander\DI\ContainerFactory($cwd);

if (!isset($tmpDir)) {
	$tmpDir = sys_get_temp_dir() . '/phpwander';
}

$additionalConfigFiles = [
	__DIR__ . '/config/config.local.neon'
];

$container = $containerFactory->create($tmpDir, $additionalConfigFiles);

/** @var \PHPWander\Analyser\Analyser $analyser */
$analyser = $container->getByType(\PHPWander\Analyser\Analyser::class);

$file = realpath(getcwd() . '/tests/cases/1/');

$errors = $analyser->analyse([
	$file,
]);

echo "<pre>---\n". htmlspecialchars(file_get_contents($file.'/index.php')) . "\n---</pre>";

foreach ($errors as $error) {
	echo sprintf('[%s:%d]: %s<br>', $error->getFile(), $error->getLine(), $error->getMessage()). PHP_EOL;
}
