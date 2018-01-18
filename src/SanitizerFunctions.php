<?php declare(strict_types = 1);

namespace PHPWander;

use PHPWander\Utils\Configuration;
use PHPWander\Utils\IConfiguration;

/**
 * @author Pavel Jurásek
 */
class SanitizerFunctions implements IConfiguration
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
				$this->getSection('xss'),
				$this->getSection('sql'),
				$this->getSection('preg'),
				$this->getSection('file'),
				$this->getSection('system'),
				$this->getSection('xpath')
			);
		}

		return $this->flat;
	}

	public function getSection(string $section): iterable
	{
		return $this->inner->getSection($section);
	}

	public function sanitizesBool(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('bool'), true);
	}

	public function sanitizesString(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('string'), true);
	}

	public function sanitizesXSS(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('xss'), true);
	}

	public function sanitizesSQL(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('sql'), true);
	}

	public function sanitizesPreg(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('preg'), true);
	}

	public function sanitizesFile(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('file'), true);
	}

	public function sanitizesSystem(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('system'), true);
	}

	public function sanitizesXpath(string $functionName): bool
	{
		return in_array($functionName, $this->getSection('xpath'), true);
	}

	public function sanitizesAll(string $functionName): bool
	{
		return in_array($functionName, $this->getAll(), true);
	}

	public function getSanitize(string $functionName): ?string
	{
		foreach ($this->inner->getTree() as $section => $functions) {
			if (in_array($functionName, $functions, true)) {
				return $section;
			}
		}

		return null;
	}

}
