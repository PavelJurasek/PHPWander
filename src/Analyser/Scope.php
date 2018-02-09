<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op\Expr;
use PHPCfg\Op\Expr\FuncCall;
use PHPCfg\Op\Expr\NsFuncCall;
use PHPCfg\Op\Stmt;
use PHPCfg\Operand\Temporary;
use PHPWander\Taint;
use PHPWander\TransitionFunction;

class Scope
{

	/** @var Scope|null */
	private $parentScope;

	/** @var TransitionFunction */
	private $transitionFunction;

	/** @var int */
	private $resultTaint = Taint::UNKNOWN;

	/** @var string */
	private $file;

	/** @var string */
	private $analysedContextFile;

	/** @var int[] */
	private $variableTaints;

	/** @var int[] */
	private $temporaries;

	/** @var Block[] */
	private $blocks = [];

	/** @var Stmt[] */
	private $statementStack = [];

	/** @var bool */
	private $negated = false;

	/** @var Func|null */
	private $func;

	/** @var FuncCall|NsFuncCall|null */
	private $funcCall;

	/**
	 * @param FuncCall|NsFuncCall|null $funcCall
	 */
	public function __construct(
		TransitionFunction $transitionFunction,
		string $file,
		string $analysedContextFile = null,
		Scope $parentScope = null,
		array $variablesTaints = [],
		array $temporaries = [],
		array $blocks = [],
		array $statementStack = [],
		bool $negated = false,
		Func $func = null,
		Expr $funcCall = null
	) {
		if ($funcCall !== null) {
			$this->assertFuncCallArgument($funcCall);
		}

		$this->transitionFunction = $transitionFunction;
		$this->file = $file;
		$this->analysedContextFile = $analysedContextFile !== null ? $analysedContextFile : $file;
		$this->parentScope = $parentScope;
		$this->variableTaints = $variablesTaints;
		$this->temporaries = $temporaries;
		$this->blocks = $blocks;
		$this->statementStack = $statementStack;
		$this->negated = $negated;
		$this->func = $func;
		$this->funcCall = $funcCall;
	}

	public function getFile(): string
	{
		return $this->file;
	}

	public function getAnalysedContextFile(): string
	{
		return $this->analysedContextFile;
	}

	public function enterFile(string $file): self
	{
		return new self(
			$this->transitionFunction,
			$file,
			$this->getFile(),
			$this,
			$this->getVariableTaints(),
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated
		);
	}

	public function leaveFile(): self
	{
		return new self(
			$this->transitionFunction,
			$this->parentScope->getFile(),
			$this->parentScope->getAnalysedContextFile(),
			$this->parentScope,
			$this->getVariableTaints(),
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated
		);
	}

	public function enterBlock(Block $block, Stmt $stmt = null, bool $negated = false): self
	{
		$blocks = $this->blocks;
		array_push($blocks, $block);

		$statements = $this->statementStack;
		if ($stmt) {
			$statements[$this->hash($block)] = $stmt;
		}

		return new self(
			$this->transitionFunction,
			$this->file,
			$this->getFile(),
			$this,
			$this->variableTaints,
			$this->getTemporaryTaints(),
			$blocks,
			$statements,
			$negated
		);
	}

	public function getCurrentBlock(): Block
	{
		return $this->blocks[count($this->blocks) - 1];
	}

	public function getParentBlock(): ?Block
	{
//		return $this->blocks[count($this->blocks) - 2];
		return $this->parentScope ? $this->parentScope->getCurrentBlock() : null;
	}

	public function getBlocks(): array
	{
		return $this->blocks;
	}

	public function leaveBlock(): self
	{
		return $this->parentScope;
	}

	public function getStatementForBlock(Block $block): ?Stmt
	{
		return $this->statementStack[$this->hash($block)];
	}

	public function getCurrentStatement(): ?Stmt
	{
		return $this->statementStack[count($this->statementStack) - 1];
	}

	public function getParentStatement(): ?Stmt
	{
		return $this->statementStack[count($this->statementStack) - 2];
	}

	public function getStatementStack(): array
	{
		return $this->statementStack;
	}

	public function isNegated(): bool
	{
		return $this->negated;
	}

//	public function enterStatement(Stmt $statement): self
//	{
//		$statements = $this->statementStack;
//		array_push($statements, $statement);
//
//		return new self(
//			$this->transitionFunction,
//			$this->file,
//			$this->getFile(),
//			$this,
//			$this->variableTaints,
//			$this->getTemporaryTaints(),
//			$this->blocks,
//			$statements
//		);
//	}

	/**
	 * @return int[]
	 */
	public function getVariableTaints(): array
	{
		return $this->variableTaints;
	}

	public function hasVariableTaint(string $variableName): bool
	{
		return isset($this->variableTaints[$variableName]);
	}

	public function getVariableTaint(string $variableName): int
	{
		if (!$this->hasVariableTaint($variableName)) {
			if ($this->parentScope) {
				return $this->parentScope->getVariableTaint($variableName);
			}

			throw new UndefinedVariable($this, $variableName);
		}

		return $this->variableTaints[$variableName];
	}

	public function assignVariable(string $variableName, int $taint): self
	{
		$variableTaints = $this->getVariableTaints();
		$variableTaints[$variableName] = $taint;

		return new self(
			$this->transitionFunction,
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated
		);
	}

	public function unsetVariable(string $variableName): self
	{
		if (!$this->hasVariableTaint($variableName)) {
			return $this;
		}
		$variableTaints = $this->getVariableTaints();
		unset($variableTaints[$variableName]);

		return new self(
			$this->transitionFunction,
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated
		);
	}

	public function hasTemporaryTaint(Temporary $temporary): bool
	{
		return isset($this->temporaries[spl_object_hash($temporary)]);
	}

	public function assignTemporary(Temporary $temporary, int $taint = Taint::UNKNOWN): self
	{
		$temporaryTaints = $this->getTemporaryTaints();
		$temporaryTaints[$this->hash($temporary)] = $taint;

		return new self(
			$this->transitionFunction,
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$this->getVariableTaints(),
			$temporaryTaints,
			$this->blocks,
			$this->statementStack,
			$this->negated
		);
	}

	public function getTemporaryTaint(Temporary $temporary): int
	{
		return $this->temporaries[spl_object_hash($temporary)];
	}

	public function getTemporaryTaints(): array
	{
		return $this->temporaries;
	}

	/**
	 * @return string[]
	 */
	public function debug(): array
	{
		$descriptions = [];
		foreach ($this->getVariableTaints() as $name => $variableTaint) {
			$descriptions[sprintf('$%s', $name)] = [
				Taint::UNKNOWN => 'unknown',
				Taint::UNTAINTED => 'untainted',
				Taint::TAINTED => 'tainted',
				Taint::BOTH => 'both',
			][$variableTaint];
		}

		return $descriptions;
	}

	public function getResultTaint(): int
	{
		return $this->resultTaint;
	}

	public function setResultTaint(int $resultTaint): void
	{
		$this->resultTaint = $resultTaint;
	}

	private function hash($object): string
	{
		return substr(md5(spl_object_hash($object)), 0, 4);
	}

	/**
	 * @param FuncCall|NsFuncCall $call
	 */
	public function enterFuncCall(Func $func, $call): self
	{
		$this->assertFuncCallArgument($call);

		return new self(
			$this->transitionFunction,
			$this->file,
			$this->getFile(),
			$this,
			$this->variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$func,
			$call
		);
	}

	public function isInFuncCall(): bool
	{
		return $this->funcCall !== null;
	}

	public function leaveFuncCall(): self
	{
		return $this->parentScope;
	}

	private function assertFuncCallArgument($call): void
	{
		if (!$call instanceof FuncCall && !$call instanceof NsFuncCall) {
			throw new \InvalidArgumentException(sprintf('%s: $call must be instance of FuncCall or NsFuncCall, %s', __METHOD__, get_class($call)));
		}
	}

}
