<?php declare(strict_types=1);

namespace PHPWander;

use PHPStan\ShouldNotHappenException;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * @author Pavel Jurásek
 */
class PhiTaint extends Taint
{

	/** @var Type */
	private $resultType;

	/** @var Type[] */
	private $types = [];

	/** @var Taint[] */
	private $taints = [];

	public function addTaint(Taint $taint): void
	{
		if ($taint instanceof PhiTaint) {
			foreach ($taint->getTaints() as $taint) {
				$this->addTaintInstance($taint);
			}
		} else {
			$this->addTaintInstance($taint);
		}
	}

	private function addTaintInstance(Taint $taint): void
	{
		$hash = $this->hash($taint);

		if (array_key_exists($hash, $this->taints)) {
			return;
		}

		$this->taints[$hash] = $taint;
		$this->types[] = $taint->getType();
	}

	public function getTaints(): array
	{
		return $this->taints;
	}

	public function getOverallTaint(): ScalarTaint
	{
		$taint = new ScalarTaint(Taint::UNKNOWN, new MixedType);

		foreach ($this->taints as $scalarTaint) {
			$taint = $taint->leastUpperBound($scalarTaint);
		}

		return $taint;
	}

	public function isTainted(): bool
	{
		return $this->getOverallTaint()->isTainted();
	}

	public function leastUpperBound(Taint $other): ScalarTaint
	{
		if ($other instanceof VectorTaint) {
			$taint = $other->getOverallTaint()->getTaint();
		} elseif ($other instanceof ScalarTaint) {
			$taint = $other->getTaint();
		} else {
			throw new \InvalidArgumentException(sprintf('Unknow instance of taint: %s', get_class($other)));
		}

		$overallTaint = $this->getOverallTaint()->getTaint();

		return new ScalarTaint($this->taintMapping[$overallTaint][$taint], TypeCombinator::union($other->getType(), ...$this->types));
	}

	public function getSingleVectorTaint(): VectorTaint
	{
		$vectors = array_filter($this->taints, function (Taint $taint) {
			return $taint instanceof VectorTaint;
		});

		if (count($vectors) !== 1) {
			throw new ShouldNotHappenException;
		}

		return reset($vectors);
	}

	public function getType(): Type
	{
		if (($this->resultType instanceof UnionType && $this->resultType->getTypes() === $this->types) || $this->resultType !== null) {
			return $this->resultType;
		}

		if (count($this->types) === 0) {
			return $this->resultType = new MixedType;
		} elseif (count($this->types) === 1) {
			return $this->resultType = $this->types[0];
		}

		return $this->resultType = new UnionType($this->types);
	}

}
