<?php declare(strict_types=1);

namespace PHPWander;

use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * @author Pavel Jurásek
 */
class VectorTaint extends Taint
{

	/** @var Type */
	private $type;

	/** @var Taint[] */
	private $taints = [];

	public function __construct(Type $type)
	{
		$this->type = $type;
	}

	public function getType(): Type
	{
		return $this->type;
	}

	public function addTaint($key, Taint $taint): void
	{
		$this->taints[$key] = $taint;
	}

	public function getTaint($key): Taint
	{
		return $this->taints[$key];
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

	public function leastUpperBound(Taint $other): ScalarTaint
	{
		if ($other instanceof VectorTaint) {
			$taint = $other->getOverallTaint();
		} elseif ($other instanceof ScalarTaint) {
			$taint = $other->getTaint();
		} else {
			throw new \InvalidArgumentException(sprintf('Unknow instance of taint: %s', get_class($other)));
		}

		return new ScalarTaint(max($this->getOverallTaint()->getTaint(), $taint), TypeCombinator::intersect($this->type, $other->getType()));
	}

	public function isTainted(): bool
	{
		return $this->getOverallTaint()->isTainted();
	}

}
