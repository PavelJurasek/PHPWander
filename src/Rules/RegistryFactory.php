<?php declare(strict_types = 1);

namespace PHPWander\Rules;

use Nette\DI\Container;

class RegistryFactory
{

	const RULE_TAG = 'phpwander.rules.rule';

	/** @var \Nette\DI\Container */
	private $container;

	/** @var FuncCallFactory */
	private $funcCallFactory;

	public function __construct(Container $container, FuncCallFactory $funcCallFactory)
	{
		$this->container = $container;
		$this->funcCallFactory = $funcCallFactory;
	}

	public function create(): Registry
	{
		$tagToService = function (array $tags) {
			return array_map(function (string $serviceName) {
				return $this->container->getService($serviceName);
			}, array_keys($tags));
		};

		$registry = new Registry(
			$tagToService($this->container->findByTag(self::RULE_TAG))
		);

		$this->funcCallFactory->create($registry);

		return $registry;
	}

}
