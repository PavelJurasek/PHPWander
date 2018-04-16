<?php declare(strict_types=1);

namespace PHPWander;

use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * @author Pavel Jurásek
 */
class ScalarTaint extends Taint
{

	/** @var int */
	private $taint;

	/** @var Type */
	private $type;

	public function __construct(int $taint, Type $type = null)
	{
		if ($type === null) {
			$type = new MixedType;
		}

		$this->taint = $taint;
		$this->type = $type;
	}

	public function getTaint(): int
	{
		return $this->taint;
	}

	public function getType(): Type
	{
		return $this->type;
	}

	public function leastUpperBound(Taint $other): ScalarTaint
	{
		if ($other instanceof VectorTaint) {
			return $other->leastUpperBound($this);
		} elseif ($other instanceof ScalarTaint) {
			$taint = $other->getTaint();
		} elseif ($other instanceof PhiTaint) {
			$taint = $other->getOverallTaint()->getTaint();
		} else {
			throw new \InvalidArgumentException(sprintf('Unknow instance of taint: %s', get_class($other)));
		}

		if (($this->getTaint() === Taint::UNTAINTED && $taint === Taint::TAINTED) || ($this->getTaint() === Taint::TAINTED && $taint === Taint::UNTAINTED)) {
			return new ScalarTaint(Taint::BOTH, TypeCombinator::union($this->type, $other->getType()));
		}

		return new ScalarTaint(max($this->getTaint(), $taint), TypeCombinator::union($this->type, $other->getType()));
	}

	public function isTainted(): bool
	{
		return $this->taint === Taint::TAINTED || $this->taint === Taint::BOTH;
	}

}
