<?php declare(strict_types=1);

namespace PHPWander;

/**
 * @author Pavel Jurásek
 */
class ScalarTaint extends Taint
{

	/** @var int */
	private $taint;

	public function __construct(int $taint)
	{
		$this->taint = $taint;
	}

	public function getTaint(): int
	{
		return $this->taint;
	}

	public function leastUpperBound(Taint $other): ScalarTaint
	{
		if ($other instanceof VectorTaint) {
			return $other->leastUpperBound($this);
		}

		/** @var ScalarTaint $other */
		return new ScalarTaint(max($this->getTaint(), $other->getTaint()));
	}

	public function isTainted(): bool
	{
		return $this->taint === Taint::TAINTED || $this->taint === Taint::BOTH;
	}

}
