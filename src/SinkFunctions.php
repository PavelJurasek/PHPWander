<?php declare(strict_types = 1);

namespace PHPWander;

use PHPWander\Utils\Configuration;
use PHPWander\Utils\IConfiguration;

/**
 * @author Pavel Jurásek
 */
class SinkFunctions implements IConfiguration
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
			$this->flat = array_merge($this->inner->getAll(), $this->inner->flatten($this->extra));
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

	public function isSensitive(string $function): bool
	{
		return array_key_exists($function, $this->getAll());
	}

	public function getSinkCategory(string $functionName): ?string
	{
		$categories = array_merge_recursive($this->inner->getTree(), $this->extra);
		foreach ($categories as $category => $functions) {
			if (array_key_exists($functionName, $functions)) {
				return $category;
			}
		}

		return null;
	}

}
