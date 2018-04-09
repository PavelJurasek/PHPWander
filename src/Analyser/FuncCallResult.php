<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPWander\PhiTaint;
use PHPWander\TransitionFunction;

/**
 * @author Pavel Jurásek
 */
class FuncCallResult
{

	/** @var FuncCallPath[] */
	private $callPaths = [];

	/** @var FuncCallPath[] */
	private $taintingCallPaths = [];

	/** @var PhiTaint */
	private $taint;

	/** @var TransitionFunction */
	private $transitionFunction;

	public function __construct(TransitionFunction $transitionFunction)
	{
		$this->taint = new PhiTaint;
		$this->transitionFunction = $transitionFunction;
	}

	public function addPath(FuncCallPath $callPath)
	{
		$this->callPaths[] = $callPath;
		$this->taint->addTaint($callPath->getTaint());

		if ($callPath->getTaint()->isTainted()) {
			$this->taintingCallPaths[] = $callPath;
		}
	}

	public function getTaintingCallPaths(): array
	{
		return $this->taintingCallPaths;
	}

	public function getTaint(): PhiTaint
	{
		return $this->taint;
	}

}
