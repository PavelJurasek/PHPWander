<?php declare(strict_types=1);

namespace PHPWander\Describer;

use PHPWander\Analyser\Scope;
use PHPWander\Printer\Printer;

/**
 * @author Pavel Jurásek
 */
interface Describer
{

	public function describe($node, Scope $scope): string;

	public function getPrinter(): Printer;

}
