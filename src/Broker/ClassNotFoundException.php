<?php declare(strict_types = 1);

namespace PHPWander\Broker;

class ClassNotFoundException extends \Exception
{

	/** @var string */
	private $className;

	public function __construct(string $functionName)
	{
		parent::__construct(sprintf('Class %s was not found while trying to analyse it - autoloading is probably not configured properly.', $functionName));
		$this->className = $functionName;
	}

	public function getClassName(): string
	{
		return $this->className;
	}

}
