<?php declare(strict_types = 1);

namespace PHPWander\Command;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPWander\Analyser\Analyser;
use PHPStan\Analyser\Error;
use PHPStan\File\FileExcluder;
use PHPStan\File\FileHelper;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Finder\Finder;

class AnalyseApplication
{

	/** @var Analyser */
	private $analyser;

	/** @var string[] */
	private $fileExtensions;

	/** @var \PHPStan\File\FileHelper */
	private $fileHelper;

	/** @var \PHPStan\File\FileExcluder */
	private $fileExcluder;

	public function __construct(
		Analyser $analyser,
		array $fileExtensions,
		FileHelper $fileHelper,
		FileExcluder $fileExcluder
	)
	{
		$this->analyser = $analyser;
		$this->fileExtensions = $fileExtensions;
		$this->fileHelper = $fileHelper;
		$this->fileExcluder = $fileExcluder;
	}

	/**
	 * @param string[] $paths
	 */
	public function analyse(array $paths, OutputStyle $style, ErrorFormatter $errorFormatter): int
	{
		if (count($paths) === 0) {
			throw new \InvalidArgumentException('At least one path must be specified to analyse.');
		}

		$errors = [];
		$files = [];

		$paths = array_map(function (string $path): string {
			return $this->fileHelper->absolutizePath($path);
		}, $paths);

		foreach ($paths as $path) {
			if (!file_exists($path)) {
				$errors[] = new Error(sprintf('<error>Path %s does not exist</error>', $path), $path, null, false);
			} elseif (is_file($path)) {
				$files[] = $this->fileHelper->normalizePath($path);
			} else {
				$finder = new Finder();
				$finder->followLinks();
				foreach ($finder->files()->name('*.{' . implode(',', $this->fileExtensions) . '}')->in($path) as $fileInfo) {
					$files[] = $this->fileHelper->normalizePath($fileInfo->getPathname());
				}
			}
		}

		$files = array_filter($files, function (string $file): bool {
			return !$this->fileExcluder->isExcludedFromAnalysing($file);
		});

		$progressStarted = false;
		$fileOrder = 0;
		$postFileCallback = function () use ($style, &$progressStarted, $files, &$fileOrder): void {
			if (!$progressStarted) {
				$style->progressStart(count($files));
				$progressStarted = true;
			}
			$style->progressAdvance();
			$fileOrder++;
		};

		$errors = array_merge($errors, $this->analyser->analyse(
			$files,
			$postFileCallback
		));

		if ($progressStarted) {
			$style->progressFinish();
		}

		$fileSpecificErrors = [];
		$notFileSpecificErrors = [];
		foreach ($errors as $error) {
			if (is_string($error)) {
				$notFileSpecificErrors[] = $error;
			} elseif ($error instanceof Error) {
				$fileSpecificErrors[] = $error;
			} else {
				throw new \PHPStan\ShouldNotHappenException();
			}
		}

		return $errorFormatter->formatErrors(
			new AnalysisResult(
				$fileSpecificErrors,
				$notFileSpecificErrors,
				true,
				$this->fileHelper->normalizePath(dirname($paths[0]))
			),
			$style
		);
	}

}
