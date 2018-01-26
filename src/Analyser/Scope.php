<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use PHPCfg\Block;
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

	public function __construct(
		TransitionFunction $transitionFunction,
		string $file,
		string $analysedContextFile = null,
		Scope $parentScope = null,
		array $variablesTaints = [],
		array $temporaries = [],
		array $blocks = []
	) {
		$this->transitionFunction = $transitionFunction;
		$this->file = $file;
		$this->analysedContextFile = $analysedContextFile !== null ? $analysedContextFile : $file;
		$this->parentScope = $parentScope;
		$this->variableTaints = $variablesTaints;
		$this->temporaries = $temporaries;
		$this->blocks = $blocks;
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
			$this
		);
	}

	public function enterBlock(Block $block): self
	{
		$blocks = $this->blocks;
		array_push($blocks, $block);

		$scope = new self(
			$this->transitionFunction,
			$this->file,
			$this->getFile(),
			$this,
			$this->variableTaints,
			$this->getTemporaryTaints(),
			$blocks
		);

		return $scope;
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
			$this->blocks
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
			$this->blocks
		);
	}

	public function hasTemporaryTaint(Temporary $temporary): bool
	{
		return isset($this->temporaries[spl_object_hash($temporary)]);
	}

	public function assignTemporary(Temporary $temporary, int $taint = Taint::UNKNOWN): self
	{
		$temporaryTaints = $this->getTemporaryTaints();
		$temporaryTaints[substr(md5(spl_object_hash($temporary)), 0, 4)] = $taint;

		return new self(
			$this->transitionFunction,
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$this->getVariableTaints(),
			$temporaryTaints,
			$this->blocks
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
			$descriptions[sprintf('$%s', $name)] = $variableTaint->describe();
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

}
