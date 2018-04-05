<?php declare(strict_types=1);

namespace PHPWander\Visitor;

use PHPCfg\AbstractVisitor;
use PHPCfg\Block;
use PHPCfg\Op;
use PHPWander\Broker\Broker;
use PHPWander\Reflection\ClassReflection;

/**
 * @author Pavel Jurásek
 */
class ClassFinder extends AbstractVisitor
{

	/** @var Broker */
	private $broker;

	public function __construct(Broker $broker)
	{
		$this->broker = $broker;
	}

	public function enterOp(Op $node, Block $block)
	{
		if ($node instanceof Op\Stmt\Class_) {
			$className = $node->name->value;

			if ($className) {
				$this->broker->addClass($className, new ClassReflection(
					$this->broker,
					(string) $className,
					(string) $node->getFile(),
					$node
				));
			}
		}
	}

}
