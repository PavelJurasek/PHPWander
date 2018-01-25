<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Func;

/**
 * @author Pavel Jurásek
 */
class FuncCallMapping
{

	/** @var Func */
	private $func;

	/** @var array of argName => taint */
	private $argTaints;

	/** @var int */
	private $taint;

	public function __construct(Func $func, array $argTaints, int $taint)
	{
		$this->func = $func;
		$this->argTaints = $argTaints;
		$this->taint = $taint;
	}

	public function getFunc(): Func
	{
		return $this->func;
	}

	public function getArgTaints(): array
	{
		return $this->argTaints;
	}

	public function getTaint(): int
	{
		return $this->taint;
	}

	public function match(Func $function, array $bindArgs): bool
	{
		if (count($bindArgs) !== count($this->argTaints) || $function !== $this->func) {
			return false;
		}

		foreach ($this->argTaints as $name => $taint) {
			if (!array_key_exists($name, $bindArgs) || $bindArgs[$name] !== $taint) {
				return false;
			}
		}

		return true;
	}

}
