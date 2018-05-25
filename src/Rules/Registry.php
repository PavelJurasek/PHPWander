<?php declare(strict_types=1);

namespace PHPWander\Rules;

/**
 * @author Pavel Jurásek
 */
class Registry
{

	/**
	 * @var \PHPWander\Rules\Rule[][]
	 */
	private $rules;

	/**
	 * @param \PHPWander\Rules\Rule[] $rules
	 */
	public function __construct(array $rules)
	{
		foreach ($rules as $rule) {
			$this->register($rule);
		}
	}

	private function register(Rule $rule)
	{
		if (!isset($this->rules[$rule->getNodeType()])) {
			$this->rules[$rule->getNodeType()] = [];
		}

		$this->rules[$rule->getNodeType()][] = $rule;
	}

	public function addRule(Rule $rule): void
	{
		$this->register($rule);
	}

	/**
	 * @param string[] $nodeTypes
	 * @return \PHPWander\Rules\Rule[]
	 */
	public function getRules(array $nodeTypes): array
	{
		$rules = [];
		foreach ($nodeTypes as $nodeType) {
			if (!isset($this->rules[$nodeType])) {
				continue;
			}

			$classRules = $this->rules[$nodeType];

			foreach ($classRules as $classRule) {
				$rules[] = $classRule;
			}
		}

		return array_values($rules);
	}

}
