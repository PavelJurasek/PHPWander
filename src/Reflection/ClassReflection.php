<?php declare(strict_types=1);

namespace PHPWander\Reflection;

use PHPCfg\Op\Stmt\Class_;
use PHPCfg\Op\Stmt\ClassMethod;
use PHPCfg\Op\Stmt\Property;
use PHPWander\Broker\Broker;
use PHPWander\Taint;
use PHPWander\VectorTaint;

/**
 * @author Pavel Jurásek
 */
class ClassReflection
{

	/** @var Broker */
	private $broker;

	/** @var string */
	private $displayName;

	/** @var Class_|null */
	private $classDefinition;

	/** @var Property[] */
	private $properties = [];

	/** @var ClassMethod[] */
	private $methods = [];

	/** @var VectorTaint|null */
	private $staticPropertiesTaint;

	public function __construct(
		Broker $broker,
		string $displayName,
		?Class_ $classDefinition
	) {
		$this->broker = $broker;
		$this->displayName = $displayName;
		$this->classDefinition = $classDefinition;

		if ($classDefinition) {
			$properties = [];
			/** @var Property $property */
			foreach (array_filter($this->classDefinition->stmts->children, function ($stmt) {
				return $stmt instanceof Property;
			}) as $property) {
				$properties[$property->name->value] = $property;
			}
			$this->properties = $properties;

			$methods = [];
			/** @var ClassMethod $method */
			foreach (array_filter($this->classDefinition->stmts->children, function ($stmt) {
				return $stmt instanceof ClassMethod;
			}) as $method) {
				$methods[$method->func->name] = $method;
			}
			$this->methods = $methods;
		}
	}

	public function getName(): string
	{
		return $this->displayName;
	}

	/** @return string[] */
	public function getPropertyNames(): array
	{
		return array_keys($this->properties);
	}

	/**
	 * @return Property[]
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}

	/** @return string[] */
	public function getMethods(): array
	{
		return array_keys($this->methods);
	}

	public function hasMethod(string $methodName): bool
	{
		return array_key_exists($methodName, $this->methods);
	}

	public function getMethod(string $methodName): ClassMethod
	{
		return $this->methods[$methodName];
	}

	public function getStaticPropertiesTaint(): ?VectorTaint
	{
		return $this->staticPropertiesTaint;
	}

	/** @internal */
	public function setStaticPropertiesTaint(VectorTaint $staticPropertiesTaint): void
	{
		$this->staticPropertiesTaint = $staticPropertiesTaint;
	}

	public function updateStaticProperty(string $property, Taint $taint): void
	{
		$this->staticPropertiesTaint->addTaint($property, $taint);
	}

	public function getFile(): string
	{
		return $this->nativeReflection->getFileName();
	}

}
