<?php declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

Tracy\Debugger::enable();

function taint($variables) {
	$callback = function (\PHPWander\Taint $taint) use (&$callback) {
		return $taint instanceof \PHPWander\ScalarTaint ? [
			\PHPWander\Taint::UNKNOWN => 'unknown',
			\PHPWander\Taint::TAINTED => 'tainted',
			\PHPWander\Taint::UNTAINTED => 'untainted',
			\PHPWander\Taint::BOTH => 'both',
		][$taint->getTaint()] : array_map($callback, $taint->getTaints());
	};

	$result = array_map($callback, $variables);
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

$file = realpath(getcwd() . '/tests/cases/3/');

$errors = $analyser->analyse([
	$file,
]);

$content = file_get_contents($file.'/index.php');

if ($content !== false) {
	$lines = explode("\n", $content);
	$pad = strlen((string) count($lines));
	$i = 1;
	$lines = array_map(function ($value) use ($pad, &$i) {
		return str_pad((string) $i++, $pad, ' ', STR_PAD_LEFT) .' '. $value;
	}, $lines);

	echo "<pre>---\n". htmlspecialchars(implode("\n", $lines)) . "\n---</pre>";
}

foreach ($errors as $error) {
	echo sprintf('[%s:%d]: %s<br>', $error->getFile(), $error->getLine(), $error->getMessage()). PHP_EOL;
}
