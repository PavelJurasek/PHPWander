<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Block;

/**
 * @author Pavel Jurásek
 */
class BlockScopeStorage
{

	/** @var array */
	private $storage = [];

	public function hasBlock(Block $block): bool
	{
		$hash = $this->hash($block);

		return array_key_exists($hash, $this->storage);
	}

	public function put(Block $block, Scope $scope): void
	{
		$this->storage[$this->hash($block)] = $scope;
	}

	public function get(Block $block): ?Scope
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
