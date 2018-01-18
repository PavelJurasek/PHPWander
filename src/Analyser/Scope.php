<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

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

	public function __construct(
		TransitionFunction $transitionFunction,
		string $file,
		string $analysedContextFile = null,
		Scope $parentScope = null,
		array $variablesTaints = []
	)
	{
		$this->transitionFunction = $transitionFunction;
		$this->file = $file;
		$this->analysedContextFile = $analysedContextFile !== null ? $analysedContextFile : $file;
		$this->parentScope = $parentScope;
		$this->variableTaints = $variablesTaints;
	}

	public function getFile(): string
	{
		return $this->file;
	}

	public function getAnalysedContextFile(): string
	{
		return $this->analysedContextFile;
	}

	public function enterFile(string $file)
	{
		return new self(
			$this->transitionFunction,

			$file,
			$this->getFile(),
			$this
		);
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

	public function assignVariable(
		string $variableName,
		int $taint
	): self
	{
		$variableTaints = $this->getVariableTaints();
		$variableTaints[$variableName] = $taint;

		return new self(
			$this->transitionFunction,
			$this->getFile(),
			$this->getAnalysedContextFile(),
			$this->parentScope,
			$variableTaints
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
			$variableTaints
		);
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
