<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Func;
use PHPCfg\Op\Expr;
use PHPCfg\Op\Expr\FuncCall;
use PHPCfg\Op\Expr\NsFuncCall;

/**
 * @author Pavel Jurásek
 */
class FuncCallStorage
{

	/** @var FuncCallMapping[] */
	private $storage = [];

	public function put(Expr $funcCall, FuncCallMapping $mapping): void
	{
		$this->assertFuncCallArgument($funcCall);
		$this->storage[$this->hash($funcCall)] = $mapping;
	}

	public function findMapping(Func $function, array $bindArgs): ?FuncCallMapping
	{
		foreach ($this->storage as $mapping) {
			if ($mapping->match($function, $bindArgs)) {
				return $mapping;
			}
		}

		return null;
	}

	public function get(Expr $funcCall): ?FuncCallMapping
	{
		$this->assertFuncCallArgument($funcCall);

		$hash = $this->hash($funcCall);
		if (array_key_exists($hash, $this->storage)) {
			return $this->storage[$hash];
		}

		return null;
	}

	private function hash($object): string
	{
		return substr(md5(spl_object_hash($object)), 0, 4);
	}

	private function assertFuncCallArgument($call): void
	{
		if (!$call instanceof FuncCall && !$call instanceof NsFuncCall && !$call instanceof Expr\MethodCall && !$call instanceof Expr\StaticCall) {
			throw new \InvalidArgumentException(sprintf('%s: $call must be instance of FuncCall, NsFuncCall, MethodCall or StaticCall, %s', __METHOD__, get_class($call)));
		}
	}

}
