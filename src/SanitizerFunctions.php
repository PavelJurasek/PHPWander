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

	/** @var array */
	private $extra;

	/** @var array|null */
	private $flat;

	public function __construct(Configuration $inner, array $extra = [])
	{
		$this->inner = $inner;
		$this->extra = $extra;
	}

	public function getAll(): array
	{
		if ($this->flat === null) {
			$inner = $this->inner->getAll();
			$this->flat = array_merge($inner, $this->inner->flatten($this->extra));
		}

		return $this->flat;
	}

	public function getSection(string $section): array
	{
		$sectionData = $this->inner->getSection($section);

		if (array_key_exists($section, $this->extra)) {
			$sectionData = array_merge($sectionData, $this->extra[$section]);
		}

		return $sectionData;
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

	public function getSanitizingCategory(string $functionName): ?string
	{
		$categories = array_merge_recursive($this->inner->getTree(), $this->extra);
		foreach ($categories as $category => $functions) {
			if (in_array($functionName, $functions, true)) {
				return $category;
			}
		}

		return null;
	}

}
