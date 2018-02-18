<?php declare(strict_types = 1);

namespace PHPWander\Parser;

use PHPCfg\Script;
use PHPCfg\Traverser;
use PHPWander\Visitor\IClassFinder;

class DirectParser implements Parser
{

	/** @var \PHPCfg\Parser */
	private $cfgParser;

	/** @var \PHPStan\Parser\Parser */
	private $astParser;

	/** @var Traverser|null */
	private $traverser;

	/** @var IClassFinder */
	private $classFinderFactory;

	public function __construct(\PHPCfg\Parser $cfgParser, \PHPStan\Parser\Parser $astParser, IClassFinder $classFinderFactory)
	{
		$this->cfgParser = $cfgParser;
		$this->astParser = $astParser;
		$this->classFinderFactory = $classFinderFactory;
	}

	public function parseFile(string $file): Script
	{
		return $this->traverseCfg($this->cfgParser->parseAst($this->astParser->parseFile($file), $file));
	}

	public function parseString(string $sourceCode, string $file): Script
	{
		return $this->traverseCfg($this->cfgParser->parseAst($this->astParser->parseString($sourceCode), $file));
	}

	private function traverseCfg(Script $script): Script
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

		if ($this->traverser === null) {
			$this->traverser = new Traverser;
			$this->traverser->addVisitor($this->classFinderFactory->create());
		}

		$this->traverser->traverse($script);

		return $script;
	}

}
