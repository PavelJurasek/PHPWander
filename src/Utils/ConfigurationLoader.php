<?php declare(strict_types=1);

namespace PHPWander\Utils;

/**
 * @author Pavel Jurásek
 */
interface ConfigurationLoader
{

	public function load(): ?array;

}
