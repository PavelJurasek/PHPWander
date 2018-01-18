<?php declare(strict_types = 1);

namespace PHPWander;

use PHPWander\Utils\Configuration;
use PHPWander\Utils\IConfiguration;

/**
 * @author Pavel Jurásek
 */
class SourceFunctions implements IConfiguration
{

	/** @var Configuration */
	private $inner;

	/** @var string[] */
	private $flat;

	public function __construct(Configuration $inner)
	{
		$this->inner = $inner;
	}

	public function getAll(): iterable
	{
		if ($this->flat === null) {
			$this->flat = array_merge(
				$this->getSection('fileInput'),
				$this->getSection('databaseInput'),
				$this->getSection('otherInput')
			);
		}

		return $this->flat;
	}

	public function getSection(string $section): iterable
	{
		return $this->inner->getSection($section);
	}

	public function getSource(string $functionName): ?string
	{
		foreach ($this->inner->getTree() as $section => $functions) {
			if (in_array($functionName, $functions, true)) {
				return $section;
			}
		}

		return null;
	}

}
