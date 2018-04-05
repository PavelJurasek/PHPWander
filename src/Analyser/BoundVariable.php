<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPWander\PhiTaint;
use PHPWander\Taint;
use PHPWander\VectorTaint;

/**
 * @author Pavel Jurásek
 */
class BoundVariable
{

	/** @var string */
	private $var;

	/** @var VectorTaint */
	private $taint;

	public function __construct(string $var, Taint $taint)
	{
		$this->var = $var;

		if ($taint instanceof PhiTaint) {
			$taint = $taint->getSingleVectorTaint();
		}
		$this->taint = $taint;
	}

	public function getVar(): string
	{
		return $this->var;
	}

	public function getTaint(): VectorTaint
	{
		return $this->taint;
	}

}
