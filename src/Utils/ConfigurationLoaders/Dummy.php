<?php declare(strict_types=1);

namespace PHPWander\Utils\ConfigurationLoaders;

use PHPWander\Utils\ConfigurationLoader;

/**
 * @author Pavel Jurásek
 */
class Dummy implements ConfigurationLoader
{

	/** @var array */
	private $data;

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function load(): ?array
	{
		return $this->data;
	}

}
