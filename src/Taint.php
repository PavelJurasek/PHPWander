<?php declare(strict_types=1);

namespace PHPWander;

/**
 * @author Pavel Jurásek
 */
abstract class Taint
{

	public const ATTR = 'taint-result';
	public const ATTR_SANITIZE = 'sanitize';
	public const ATTR_SOURCE = 'source';
	public const ATTR_SINK = 'sink';
	public const ATTR_TAINT = 'taints';
	public const ATTR_THREATS = 'threats';

	public const UNKNOWN = 0;
	public const UNTAINTED = 1;
	public const TAINTED = 2;
	public const BOTH = 3;

	abstract public function leastUpperBound(Taint $other): ScalarTaint;

	abstract public function isTainted(): bool;

}
