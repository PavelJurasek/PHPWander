<?php declare(strict_types=1);

namespace PHPWander\Visitor;

/**
 * @author Pavel Jurásek
 */
interface IClassFinder
{

	public function create(): ClassFinder;

}
