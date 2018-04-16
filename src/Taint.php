<?php declare(strict_types=1);

namespace PHPWander;

use PHPStan\Type\Type;

/**
 * @author Pavel Jurásek
 */
abstract class Taint
{

	public const ATTR = 'taint-result';
	public const ATTR_TYPE = 'taint-type';
	public const ATTR_SANITIZE = 'sanitize';
	public const ATTR_SOURCE = 'source';
	public const ATTR_SINK = 'sink';
	public const ATTR_TAINT = 'taints';
	public const ATTR_THREATS = 'threats';

	public const UNKNOWN = 0;
	public const UNTAINTED = 1;
	public const TAINTED = 2;
	public const BOTH = 3;

	protected $taintMapping = [
		Taint::UNKNOWN => [
			Taint::UNKNOWN => Taint::UNKNOWN,
			Taint::UNTAINTED => Taint::UNTAINTED,
			Taint::TAINTED => Taint::TAINTED,
			Taint::BOTH => Taint::BOTH,
		],
		Taint::UNTAINTED => [
			Taint::UNKNOWN => Taint::UNTAINTED,
			Taint::UNTAINTED => Taint::UNTAINTED,
			Taint::TAINTED => Taint::BOTH,
			Taint::BOTH => Taint::BOTH,
		],
		Taint::TAINTED => [
			Taint::UNKNOWN => Taint::TAINTED,
			Taint::UNTAINTED => Taint::BOTH,
			Taint::TAINTED => Taint::TAINTED,
			Taint::BOTH => Taint::BOTH,
		],
		Taint::BOTH => [
			Taint::UNKNOWN => Taint::BOTH,
			Taint::UNTAINTED => Taint::BOTH,
			Taint::TAINTED => Taint::BOTH,
			Taint::BOTH => Taint::BOTH,
		],
	];

	abstract public function leastUpperBound(Taint $other): ScalarTaint;

	abstract public function isTainted(): bool;

	abstract public function getType(): Type;

	protected function hash($object): string
	{
		return substr(md5(spl_object_hash($object)), 0, 4);
	}

}
