<?php declare(strict_types = 1);

namespace PHPWander;

use PHPWander\Utils\Configuration;
use PHPWander\Utils\IConfiguration;

/**
 * @author Pavel Jurásek
 */
class TaintFunctions implements IConfiguration
{

	/** @var Configuration */
	private $inner;

	/** @var array|null */
	private $flat;

	public function __construct(Configuration $inner)
	{
		$this->inner = $inner;
	}

	public function getAll(): array
	{
		if ($this->flat === null) {
			$this->flat = $this->getSection('string');
		}

		return $this->flat;
	}

	public function getSection(string $section): array
	{
		return $this->inner->getSection($section);
	}

	public function getTaint(string $functionName): ?string
	{
		foreach ($this->inner->getTree() as $section => $functions) {
			if (in_array($functionName, $functions, true)) {
				return $section;
			}
		}

		return null;
	}

}
