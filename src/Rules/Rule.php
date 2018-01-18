<?php declare(strict_types = 1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPWander\Analyser\Scope;

interface Rule
{

	/**
	 * @return string Class implementing \PhpParser\Node
	 */
	public function getNodeType(): string;

	/**
	 * @return string[] errors
	 */
	public function processNode(Op $node, Scope $scope): array;

}
