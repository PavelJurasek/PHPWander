<?php declare(strict_types=1);

namespace PHPWander\DI;

use Nette\Configurator;
use Nette\DI\Extensions\DecoratorExtension;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\Extensions\PhpExtension;
use PHPStan\File\FileHelper;
use Tracy\Bridges\Nette\TracyExtension;

class ContainerFactory
{

	/** @var string */
	private $currentWorkingDirectory;

	/** @var string */
	private $rootDirectory;

	/** @var string */
	private $configDirectory;

	public function __construct(string $currentWorkingDirectory)
	{
		$this->currentWorkingDirectory = $currentWorkingDirectory;
		$fileHelper = new FileHelper($currentWorkingDirectory);
		$this->rootDirectory = $fileHelper->normalizePath(__DIR__ . '/../..');
		$this->configDirectory = $this->rootDirectory . '/config';
	}

	public function create(
		string $tempDirectory,
		array $additionalConfigFiles
	): \Nette\DI\Container
	{
		$configurator = new Configurator;
		$configurator->defaultExtensions = [
			'php' => PhpExtension::class,
			'extensions' => ExtensionsExtension::class,
			'decorator' => DecoratorExtension::class,
			'tracy' => TracyExtension::class,
		];
		$configurator->setDebugMode(true);
		$configurator->setTempDirectory($tempDirectory);
		$configurator->addParameters([
			'rootDir' => $this->rootDirectory,
			'currentWorkingDirectory' => $this->currentWorkingDirectory,
			'cliArgumentsVariablesRegistered' => ini_get('register_argc_argv') === '1',
			'tmpDir' => $tempDirectory,
		]);
		$configurator->addConfig($this->configDirectory . '/config.neon');
		foreach ($additionalConfigFiles as $additionalConfigFile) {
			$configurator->addConfig($additionalConfigFile);
		}

		return $configurator->createContainer();
	}

	public function getCurrentWorkingDirectory(): string
	{
		return $this->currentWorkingDirectory;
	}

	public function getRootDirectory(): string
	{
		return $this->rootDirectory;
	}

	public function getConfigDirectory(): string
	{
		return $this->configDirectory;
	}

}
