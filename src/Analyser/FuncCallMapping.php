<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Func;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class FuncCallMapping
{

	/** @var Func */
	private $func;

	/** @var array of argName => taint */
	private $argTaints;

	/** @var FuncCallResult */
	private $funcCallResult;

	/** @var Taint */
	private $taint;

	public function __construct(Func $func, array $argTaints, FuncCallResult $funcCallResult, Taint $taint)
	{
		$this->func = $func;
		$this->argTaints = $argTaints;
		$this->funcCallResult = $funcCallResult;
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

	public function getFuncCallResult(): FuncCallResult
	{
		return $this->funcCallResult;
	}

	public function getTaint(): Taint
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
