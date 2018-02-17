<?php declare(strict_types=1);

namespace PHPWander;

/**
 * @author Pavel Jurásek
 */
class VectorTaint extends Taint
{

	/** @var ScalarTaint[] */
	private $taints = [];

	public function addTaint($key, ScalarTaint $taint): void
	{
		$this->taints[$key] = $taint;
	}

	public function getTaint($key): int
	{
		return $this->taints[$key]->getTaint();
	}

	private function getOverallTaint(): ScalarTaint
	{
		$taint = new ScalarTaint(Taint::UNKNOWN);

		foreach ($this->taints as $scalarTaint) {
			$taint = $taint->leastUpperBound($scalarTaint);
		}

		return $taint;
	}

	public function leastUpperBound(Taint $other): ScalarTaint
	{
		if ($other instanceof VectorTaint) {
			$taint = $other->getOverallTaint();
		} else {
			/** @var ScalarTaint $other */
			$taint = $other->getTaint();
		}

		return new ScalarTaint(max($this->getOverallTaint(), $taint));
	}

	public function isTainted(): bool
	{
		return $this->getOverallTaint()->isTainted();
	}

}
