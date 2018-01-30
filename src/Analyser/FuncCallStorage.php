<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op\Expr\FuncCall;

/**
 * @author Pavel Jurásek
 */
class FuncCallStorage
{

	/** @var FuncCallMapping[] */
	private $storage = [];

	public function put(FuncCall $funcCall, FuncCallMapping $mapping): void
	{
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

	public function get(FuncCall $funcCall): ?FuncCallMapping
	{
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

}
