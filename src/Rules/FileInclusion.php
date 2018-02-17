<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPWander\Analyser\Scope;
use PHPWander\ScalarTaint;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class FileInclusion extends AbstractRule implements Rule
{

	/**
	 * @return string Class implementing \PhpParser\Node
	 */
	public function getNodeType(): string
	{
		return Op\Expr\Include_::class;
	}

	/**
	 * @param Op\Expr\Include_ $node
	 * @return string[] errors
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		if ($node->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN))->isTainted() && in_array('file', $node->getAttribute('threats'), true)) {
			return [
				sprintf('File inclusion is tainted by non-static string.'),
			];
		}

		return [];
	}

}
