<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use PHPCfg\Op;
use PHPStan\Analyser\Error;
use PHPStan\File\FileExcluder;
use PHPStan\File\FileHelper;
use PHPWander\Parser\Parser;
use PHPWander\Rules\Registry;
use PHPWander\TransitionFunction;
use Symfony\Component\Finder\Finder;

class Analyser
{

	/** @var TransitionFunction */
	private $transitionFunction;

	/** @var Parser */
	private $parser;

	/** @var Registry */
	private $registry;

	/** @var NodeScopeResolver */
	private $nodeScopeResolver;

	/** @var string[] */
	private $ignoreErrors;

	/** @var string|null */
	private $bootstrapFile;

	/** @var bool */
	private $reportUnmatchedIgnoredErrors;

	/** @var string */
	private $rootDir;

	/** @var FileHelper */
	private $fileHelper;

	/** @var FileExcluder */
	private $fileExcluder;

	/** @var array */
	private $fileExtensions;

	public function __construct(
		string $rootDir,
		array $fileExtensions,
		TransitionFunction $transitionFunction,
		Parser $parser,
		Registry $registry,
		NodeScopeResolver $nodeScopeResolver,
		FileHelper $fileHelper,
		FileExcluder $fileExcluder,
		array $ignoreErrors = [],
		string $bootstrapFile = null,
		bool $reportUnmatchedIgnoredErrors = false
	)
	{
		$this->rootDir = $rootDir;
		$this->fileExtensions = $fileExtensions;
		$this->transitionFunction = $transitionFunction;
		$this->parser = $parser;
		$this->registry = $registry;
		$this->nodeScopeResolver = $nodeScopeResolver;
		$this->ignoreErrors = $ignoreErrors;
		$this->bootstrapFile = $bootstrapFile !== null ? $fileHelper->normalizePath($bootstrapFile) : null;
		$this->reportUnmatchedIgnoredErrors = $reportUnmatchedIgnoredErrors;
		$this->fileHelper = $fileHelper;
		$this->fileExcluder = $fileExcluder;
	}

	/**
	 * @param string[] $filesOrPaths
	 * @return string[]|\PHPStan\Analyser\Error[] errors
	 */
	public function analyse(array $filesOrPaths, \Closure $progressCallback = null): array
	{
		$errors = [];

		if ($this->bootstrapFile !== null) {
			if (!is_file($this->bootstrapFile)) {
				return [
					sprintf('Bootstrap file %s does not exist.', $this->bootstrapFile),
				];
			}
			try {
				require_once $this->bootstrapFile;
			} catch (\Throwable $e) {
				return [$e->getMessage()];
			}
		}

		foreach ($this->ignoreErrors as $ignoreError) {
			try {
				\Nette\Utils\Strings::match('', $ignoreError);
			} catch (\Nette\Utils\RegexpException $e) {
				$errors[] = $e->getMessage();
			}
		}

		if (count($errors) > 0) {
			return $errors;
		}

		$files = [];
		$onlyFiles = true;
		foreach ($filesOrPaths as $path) {
			if (!file_exists($path)) {
				$errors[] = new Error(sprintf('<error>Path %s does not exist</error>', $path), $path, null, false);
			} elseif (is_file($path)) {
				$files[] = $this->fileHelper->normalizePath($path);
			} else {
				$finder = new Finder();
				$finder->followLinks();
				/** @var \SplFileInfo $fileInfo */
				foreach ($finder->files()->name('*.{' . implode(',', $this->fileExtensions) . '}')->in($path) as $fileInfo) {
					$files[] = $this->fileHelper->normalizePath($fileInfo->getPathname());
					$onlyFiles = false;
				}
			}
		}
		$files = array_filter($files, function (string $file): bool {
			return !$this->fileExcluder->isExcludedFromAnalysing($file);
		});

		foreach ($files as $file) {
			try {
				if ($this->nodeScopeResolver->isFileAnalysed($file)) {
					continue;
				}

				$this->nodeScopeResolver->addAnalysedFile($file);
				$script = $this->parser->parseFile($file);

				$fileErrors = [];
				$this->nodeScopeResolver->processScript(
					$script,
					new Scope($this->transitionFunction, $file),
					function (Op $node, Scope $scope) use (&$fileErrors) {
						$classes = array_merge([get_class($node)], class_parents($node));
						foreach ($this->registry->getRules($classes) as $rule) {
							$ruleErrors = $this->createErrors(
								$node,
								$scope->getFile(),
								$rule->processNode($node, $scope)
							);
							$fileErrors = array_merge($fileErrors, $ruleErrors);
						}
					}
				);
				if ($progressCallback !== null) {
					$progressCallback($file);
				}

				dump($script);

				$errors = array_merge($errors, $fileErrors);
			} catch (\PhpParser\Error $e) {
				$errors[] = new Error($e->getMessage(), $file, $e->getStartLine() !== -1 ? $e->getStartLine() : null);
			} catch (\PHPStan\AnalysedCodeException $e) {
				$errors[] = new Error($e->getMessage(), $file);
			} catch (\Throwable $t) {
				$errors[] = new Error(sprintf('Internal error: %s', $t->getMessage()), $file);
				throw $t;
			}
		}

		$unmatchedIgnoredErrors = $this->ignoreErrors;
		$errors = array_values(array_filter($errors, function ($error) use (&$unmatchedIgnoredErrors): bool {
			foreach ($this->ignoreErrors as $i => $ignore) {
				if (\Nette\Utils\Strings::match(is_scalar($error) ? $error : $error->getMessage(), $ignore) !== null) {
					unset($unmatchedIgnoredErrors[$i]);
					return false;
				}
			}

			return true;
		}));

		if (!$onlyFiles && $this->reportUnmatchedIgnoredErrors) {
			foreach ($unmatchedIgnoredErrors as $unmatchedIgnoredError) {
				$errors[] = sprintf(
					'Ignored error pattern %s was not matched in reported errors.',
					$unmatchedIgnoredError
				);
			}
		}

		return $errors;
	}

	/**
	 * @param Op $node
	 * @param string $file
	 * @param string[] $messages
	 * @return \PHPStan\Analyser\Error[]
	 */
	private function createErrors(Op $node, string $file, array $messages): array
	{
		$file = preg_replace("~$this->rootDir/~i",  '', $file);

		$errors = [];
		foreach ($messages as $message) {
			$errors[] = new Error(preg_replace("~$this->rootDir/~i",  '', $message), $file, $node->getLine());
		}

		return $errors;
	}

}
