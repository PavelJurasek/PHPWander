<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Block;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class BlockTaintStorage
{

	/** @var array */
	private $storage = [];

	public function hasBlock(Block $block): bool
	{
		$hash = $this->hash($block);

		return array_key_exists($hash, $this->storage);
	}

	public function put(Block $block, Taint $taint): void
	{
		$this->storage[$this->hash($block)] = $taint;
	}

	public function get(Block $block): ?Taint
	{
		$hash = $this->hash($block);
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
