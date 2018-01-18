<?php declare(strict_types = 1);

namespace PHPWander\Parser;

use PHPCfg\Script;
use PHPCfg\Traverser;
use PHPCfg\Visitor\CallFinder;
use PHPCfg\Visitor\DeclarationFinder;
use PHPCfg\Visitor\VariableFinder;

class DirectParser implements Parser
{

	/** @var \PHPCfg\Parser */
	private $cfgParser;

	/** @var \PHPStan\Parser\Parser */
	private $astParser;

	/** @var Traverser */
	private $traverser;

	public function __construct(\PHPCfg\Parser $cfgParser, \PHPStan\Parser\Parser $astParser)
	{
		$this->cfgParser = $cfgParser;
		$this->astParser = $astParser;
	}

	public function parseFile(string $file): Script
	{
		return $this->traverse($this->cfgParser->parseAst($this->astParser->parseFile($file), $file));
	}

	public function parseString(string $sourceCode, string $file): Script
	{
		return $this->traverse($this->cfgParser->parseAst($this->astParser->parseString($sourceCode), $file));
	}

	private function traverse(Script $script): Script
	{
//		$declarations = new DeclarationFinder;
//		$variables = new VariableFinder;
//		$calls = new CallFinder;
//
//		$this->traverser = new Traverser;
//		$this->traverser->addVisitor($declarations);
//		$this->traverser->addVisitor($variables);
//		$this->traverser->addVisitor($calls);
//
//		$this->traverser->traverse($script);
//
//		dump($declarations);
//		dump($variables);
//		dump($calls);
//		die;

		return $script;
	}

}
