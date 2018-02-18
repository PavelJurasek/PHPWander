<?php declare(strict_types = 1);

namespace PHPWander\Command;

use Nette\DI\Config\Loader;
use Nette\DI\Helpers;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\ErrorsConsoleStyle;
use PHPStan\File\FileHelper;
use PHPWander\DI\ContainerFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

class AnalyseCommand extends \Symfony\Component\Console\Command\Command
{

	private const NAME = 'analyse';

	protected function configure(): void
	{
		$this->setName(self::NAME)
			 ->setDescription('Analyses source code')
			 ->setDefinition([
				 new InputArgument('paths', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Paths with source code to run analysis on'),
				 new InputOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Path to project configuration file'),
				 new InputOption(ErrorsConsoleStyle::OPTION_NO_PROGRESS, null, InputOption::VALUE_NONE, 'Do not show progress bar, only results'),
				 new InputOption('error-format', null, InputOption::VALUE_REQUIRED, 'Format in which to print the result of the analysis', 'table'),
				 new InputOption('autoload-file', 'a', InputOption::VALUE_REQUIRED, 'Project\'s additional autoload file path'),
				 new InputOption('autoload', null, InputOption::VALUE_NONE, 'Paths with source code to run analysis on will be autoloaded'),
			 ]);
	}


	public function getAliases(): array
	{
		return ['analyze'];
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$consoleStyle = new ErrorsConsoleStyle($input, $output);

		$currentWorkingDirectory = getcwd();
		if ($currentWorkingDirectory === false) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		$fileHelper = new FileHelper($currentWorkingDirectory);

		$autoloadFile = $input->getOption('autoload-file');
		if ($autoloadFile !== null && is_file($autoloadFile)) {
			$autoloadFile = $fileHelper->normalizePath($autoloadFile);
			if (is_file($autoloadFile)) {
				require_once $autoloadFile;
			}
		}

		$paths = $input->getArgument('paths');
		$projectConfigFile = $input->getOption('configuration');
		if ($projectConfigFile === null) {
			foreach (['phpwander.neon', 'phpwander.local.neon'] as $discoverableConfigName) {
				$discoverableConfigFile = $currentWorkingDirectory . DIRECTORY_SEPARATOR . $discoverableConfigName;
				if (is_file($discoverableConfigFile)) {
					$projectConfigFile = $discoverableConfigFile;
					$output->writeln(sprintf('Note: Using configuration file %s.', $projectConfigFile));
					break;
				}
			}
		}

		$containerFactory = new ContainerFactory($currentWorkingDirectory);

		if ($projectConfigFile !== null) {
			if (!is_file($projectConfigFile)) {
				$output->writeln(sprintf('Project config file at path %s does not exist.', $projectConfigFile));
				return 1;
			}

			$loader = new Loader;
			$projectConfig = $loader->load($projectConfigFile);
			$defaultParameters = [
				'rootDir' => $containerFactory->getRootDirectory(),
				'currentWorkingDirectory' => $containerFactory->getCurrentWorkingDirectory(),
			];

			if (isset($projectConfig['parameters']['tmpDir'])) {
				$tmpDir = Helpers::expand($projectConfig['parameters']['tmpDir'], $defaultParameters);
			}
			if (count($paths) === 0 && isset($projectConfig['parameters']['paths'])) {
				$paths = Helpers::expand($projectConfig['parameters']['paths'], $defaultParameters);
			}
		}

		$additionalConfigFiles = [];

		if ($projectConfigFile !== null) {
			$additionalConfigFiles[] = $projectConfigFile;
		}

		if (!isset($tmpDir)) {
			$tmpDir = sys_get_temp_dir() . '/phpstan';
			if (!@mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
				$consoleStyle->error(sprintf('Cannot create a temp directory %s', $tmpDir));
				return 1;
			}
		}

		$container = $containerFactory->create($tmpDir, $additionalConfigFiles);

		$errorFormat = $input->getOption('error-format');
		$errorFormatterServiceName = sprintf('errorFormatter.%s', $errorFormat);
		if (!$container->hasService($errorFormatterServiceName)) {
			$consoleStyle->error(sprintf(
				'Error formatter "%s" not found. Available error formatters are: %s',
				$errorFormat,
				implode(', ', array_map(function (string $name) {
					return substr($name, strlen('errorFormatter.'));
				}, $container->findByType(ErrorFormatter::class)))
			));
			return 1;
		}

		/** @var ErrorFormatter $errorFormatter */
		$errorFormatter = $container->getService($errorFormatterServiceName);

		$this->setUpSignalHandler($consoleStyle);

		foreach ($container->parameters['autoloadFiles'] as $autoloadFile) {
			require_once $fileHelper->normalizePath($autoloadFile);
		}

		if ($input->getOption('autoload')) {
			foreach ($paths as $path) {
				$container->parameters['autoloadDirectories'][] = $path;
			}
		}

		if (count($container->parameters['autoloadDirectories']) > 0) {
			$robotLoader = new \Nette\Loaders\RobotLoader();
			$robotLoader->acceptFiles = array_map(function (string $extension): string {
				return sprintf('*.%s', $extension);
			}, $container->parameters['fileExtensions']);

			$robotLoader->setTempDirectory($tmpDir);
			foreach ($container->parameters['autoloadDirectories'] as $directory) {
				$robotLoader->addDirectory($fileHelper->normalizePath($directory));
			}

			$robotLoader->register();
		}

		/** @var AnalyseApplication $application */
		$application = $container->getByType(AnalyseApplication::class);
		return $application->analyse(
			$paths,
			$consoleStyle,
			$errorFormatter
		);
	}

	private function setUpSignalHandler(StyleInterface $consoleStyle): void
	{
		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGINT, function () use ($consoleStyle): void {
				$consoleStyle->newLine();
				exit(1);
			});
		}
	}

}
