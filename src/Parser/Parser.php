<?php declare(strict_types = 1);

namespace PHPWander\Parser;

use PHPCfg\Script;
use PhpParser\Node;

interface Parser
{

	/**
	 * @param string $file path to a file to parse
	 */
	public function parseFile(string $file): Script;

	/**
	 * @param string $sourceCode
	 */
	public function parseString(string $sourceCode, string $file): Script;

	/**
	 * @param Node[] $nodes
	 */
//	public function parseNodes(array $nodes, Block $block): Block;

}
