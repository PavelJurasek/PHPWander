<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op\Expr;
use PHPCfg\Op\Expr\FuncCall;
use PHPCfg\Op\Expr\NsFuncCall;
use PHPCfg\Op\Stmt;
use PHPCfg\Operand;
use PHPCfg\Operand\Temporary;
use PHPWander\ScalarTaint;
use PHPWander\Taint;

class Scope
{

	/** @var Scope|null */
	private $parentScope;

	/** @var Taint */
	private $resultTaint;

	/** @var string */
	private $file;

	/** @var string */
	private $analysedContextFile;

	/** @var Taint[] */
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

	/** @var BoundVariable|null */
	private $boundVariable;

	/**
	 * @param FuncCall|NsFuncCall|Expr\MethodCall|null $funcCall
	 */
	public function __construct(
		string $file,
		string $analysedContextFile = null,
		Scope $parentScope = null,
		array $variablesTaints = [],
		array $temporaries = [],
		array $blocks = [],
		array $statementStack = [],
		bool $negated = false,
		Func $func = null,
		Expr $funcCall = null,
		BoundVariable $boundVariable = null
	) {
		if ($funcCall !== null) {
			$this->assertFuncCallArgument($funcCall);
		}

		$this->resultTaint = new ScalarTaint(Taint::UNKNOWN);

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
		$this->boundVariable = $boundVariable;
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
			$file,
			$this->getFile(),
			$this,
			$this->getVariableTaints(),
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
		);
	}

	public function leaveFile(): self
	{
		return new self(
			$this->parentScope->getFile(),
			$this->parentScope->getAnalysedContextFile(),
			$this->parentScope,
			$this->getVariableTaints(),
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
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
			$this->file,
			$this->getFile(),
			$this,
			$this->variableTaints,
			$this->getTemporaryTaints(),
			$blocks,
			$statements,
			$negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
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
		$parentScope = $this->parentScope;
		$parentScope->setResultTaint($this->getResultTaint());

		return $parentScope;
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
	 * @return Taint[]
	 */
	public function getVariableTaints(): array
	{
		return $this->variableTaints;
	}

	public function hasVariableTaint(string $variableName): bool
	{
		return isset($this->variableTaints[$variableName]);
	}

	public function getVariableTaint(string $variableName): Taint
	{
		if ($this->boundVariable && $match = $this->matchBoundVariable($variableName)) {
			return $this->boundVariable->getTaint()->getTaint($match);
		}

		if (!$this->hasVariableTaint($variableName)) {
			if ($this->parentScope) {
				return $this->parentScope->getVariableTaint($variableName);
			}

			return new ScalarTaint(Taint::UNKNOWN);
		}

		return $this->variableTaints[$variableName];
	}

	public function assignVariable(string $variableName, Taint $taint): self
	{
		$variableTaints = $this->getVariableTaints();

		if ($this->boundVariable) {
			$match = $this->matchBoundVariable($variableName);
			if ($match) {
				$this->boundVariable->getTaint()->addTaint($match, $taint);
			}
		}
		$variableTaints[$variableName] = $taint;

		return new self(
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
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
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
		);
	}

	public function hasTemporaryTaint(Temporary $temporary): bool
	{
		return array_key_exists($this->hash($temporary), $this->temporaries);
	}

	public function assignTemporary(Operand $temporary, Taint $taint = null): self
	{
		if (!$temporary instanceof Temporary) {
			return $this;
		}

		if ($taint === null) {
			$taint = new ScalarTaint(Taint::UNKNOWN);
		}

		$temporaryTaints = $this->getTemporaryTaints();
		$temporaryTaints[$this->hash($temporary)] = $taint;

		return new self(
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$this->getVariableTaints(),
			$temporaryTaints,
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$this->func,
			$this->funcCall,
			$this->boundVariable
		);
	}

	public function getTemporaryTaint(Temporary $temporary): Taint
	{
		return $this->temporaries[$this->hash($temporary)];
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

	public function getResultTaint(): Taint
	{
		return $this->resultTaint;
	}

	public function setResultTaint(Taint $resultTaint): void
	{
		$this->resultTaint = $resultTaint;
	}

	private function hash($object): string
	{
		return substr(md5(spl_object_hash($object)), 0, 4);
	}

	/**
	 * @param FuncCall|NsFuncCall|Expr\MethodCall $call
	 */
	public function enterFuncCall(Func $func, $call, ?BoundVariable $boundVariable = null): self
	{
		$this->assertFuncCallArgument($call);

		return new self(
			$this->file,
			$this->getFile(),
			$this,
			$this->variableTaints,
			$this->getTemporaryTaints(),
			$this->blocks,
			$this->statementStack,
			$this->negated,
			$func,
			$call,
			$boundVariable
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

	public function getBoundVariable(): ?BoundVariable
	{
		return $this->boundVariable;
	}

	private function matchBoundVariable(string $variableName): ?string
	{
		$match = preg_match(sprintf('~^\%s->([a-zA-Z_][a-zA-Z0-9_]+)\z~', $this->boundVariable->getVar()), $variableName, $m);

		return $match === 1 ? $m[1] : null;
	}

	private function assertFuncCallArgument($call): void
	{
		if (!$call instanceof FuncCall && !$call instanceof NsFuncCall && !$call instanceof Expr\MethodCall) {
			throw new \InvalidArgumentException(sprintf('%s: $call must be instance of FuncCall or NsFuncCall, %s', __METHOD__, get_class($call)));
		}
	}

}
