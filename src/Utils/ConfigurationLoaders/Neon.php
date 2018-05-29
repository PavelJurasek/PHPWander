<?php declare(strict_types=1);

namespace PHPWander\Utils\ConfigurationLoaders;

use Nette\DI\Helpers;
use PHPWander\Utils\ConfigurationLoader;

/**
 * @author Pavel Jurásek
 */
class Neon implements ConfigurationLoader
{

	/** @var string */
	private $file;

	/** @var \Nette\Neon\Neon */
	private $neon;

	/** @var array */
	private $data;

	public function __construct(string $file, \Nette\Neon\Neon $neon)
	{
		if (!is_file($file)) {
			throw new \FileNotFoundException($file);
		}

		$this->file = $file;
		$this->neon = $neon;
	}

	public function load(): ?array
	{
		if ($this->data === null) {
			$this->data = $this->neon->decode(file_get_contents($this->file));

			$params = [];
			if (array_key_exists('includes', $this->data)) {
				foreach ($this->data['includes'] as $file) {
					$params = array_merge($params, $this->neon->decode(file_get_contents(dirname($this->file) .DIRECTORY_SEPARATOR. $file)));
				}

				unset($this->data['includes']);
			}

			$this->data = Helpers::expand($this->data, array_merge($this->data, $params), true);
		}

		return $this->data;
	}

}
