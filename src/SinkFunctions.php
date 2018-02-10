<?php declare(strict_types = 1);

namespace PHPWander;

use PHPCfg\Op\Expr\FuncCall;
use PHPCfg\Operand\Literal;
use PHPWander\Utils\Configuration;
use PHPWander\Utils\IConfiguration;

/**
 * @author Pavel Jurásek
 */
class SinkFunctions implements IConfiguration
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
			$this->flat = $this->inner->getAll();
		}

		return $this->flat;
	}

	public function getSection(string $section): array
	{
		return $this->inner->getSection($section);
	}

	public function isSensitive(FuncCall $node): bool
	{
		$name = $node->name instanceof Literal ? $node->name->value : $node;

		if (!array_key_exists($name, $this->getAll())) {
			return false;
		}

		return true;

		$args = $this->getAll()[$name][0];

		foreach ($args as $argNumber) {
			if ($argNumber === 0) {
				return true;
			}

//			if ()
		}

		return false;
	}

	public function getSink(string $functionName): ?string
	{
		foreach ($this->inner->getTree() as $section => $functions) {
			if (in_array($functionName, $functions, true)) {
				return $section;
			}
		}

		return null;
	}

}
