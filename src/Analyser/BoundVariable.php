<?php declare(strict_types=1);

namespace PHPWander\Analyser;

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

	public function __construct(string $var, VectorTaint $taint)
	{
		$this->var = $var;
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
