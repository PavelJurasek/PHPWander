<?php declare(strict_types = 1);

namespace PHPWander\Utils;

/**
 * @author Pavel Jurásek
 */
class Configuration implements IConfiguration
{

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var array|null */
	private $tree;

	/** @var array|null */
	private $flat;

	public function __construct(ConfigurationLoader $configurationLoader)
	{
		$this->configurationLoader = $configurationLoader;
	}

	public function getTree(): array
	{
		if ($this->tree === null) {
			$this->tree = $this->configurationLoader->load();
		}

		return $this->tree;
	}

	public function getAll(): array
	{
		if ($this->flat === null) {
			$this->flat = $this->flatten($this->getTree());
		}

		return $this->flat;
	}

	public function getSection(string $section): array
	{
		return $this->configurationLoader->load()[$section];
	}

	public function flatten(array $array): array
	{
		if (count($array) === 0) {
			return $array;
		}

		return array_merge(...array_values($array));
	}

}
