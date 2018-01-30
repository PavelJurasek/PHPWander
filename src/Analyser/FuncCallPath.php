<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPCfg\Op;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class FuncCallPath
{

	public const EVAL_FALSE = 0;
	public const EVAL_TRUE = 1;
	public const EVAL_UNCONDITIONAL = 2;

	/** @var self|null */
	private $parent;

	/** @var self[] */
	private $children = [];

	/** @var Op */
	private $statement;

	/** @var int */
	private $evaluation;

	/** @var int */
	private $taint;

	public function __construct(?self $parent, Op $statement, int $evaluation)
	{
		$this->parent = $parent;
		$this->statement = $statement;
		$this->evaluation = $evaluation;
		$this->taint = Taint::UNKNOWN;

		if ($parent) {
			$parent->addChild($this);
		}
	}

	public function getParent(): ?FuncCallPath
	{
		return $this->parent;
	}

	/** @internal */
	protected function addChild(self $child): void
	{
		$this->children[] = $child;
	}

	/** @return self[] */
	public function getChildren(): array
	{
		return $this->children;
	}

	public function getStatement(): Op
	{
		return $this->statement;
	}

	public function getEvaluation(): int
	{
		return $this->evaluation;
	}

	public function getTaint(): int
	{
		return $this->taint;
	}

	public function setTaint(int $taint): void
	{
		$this->taint = $taint;
		if ($this->parent) {
			$this->parent->setTaint($taint);
		}
	}

}
