<?php declare(strict_types = 1);

namespace PHPWander\Parser;

use PHPCfg\Script;

class CachedParser implements Parser
{

	/** @var Parser */
	private $originalParser;

	/** @var mixed[] */
	private $cachedNodesByFile = [];

	/** @var mixed[] */
	private $cachedNodesByString = [];

	public function __construct(Parser $originalParser)
	{
		$this->originalParser = $originalParser;
	}

	public function parseFile(string $file): Script
	{
		if (!isset($this->cachedNodesByFile[$file])) {
			$this->cachedNodesByFile[$file] = $this->originalParser->parseFile($file);
		}

		return $this->cachedNodesByFile[$file];
	}

	public function parseString(string $sourceCode, string $file): Script
	{
		if (!isset($this->cachedNodesByString[$sourceCode])) {
			$this->cachedNodesByString[$sourceCode] = $this->originalParser->parseString($sourceCode, $file);
		}

		return $this->cachedNodesByString[$sourceCode];
	}

}
