<?php declare(strict_types = 1);

namespace PHPWander\Broker;

use PHPWander\Parser\Parser;
use PHPWander\Reflection\ClassReflection;
use ReflectionClass;

class Broker
{

	/** @var ClassReflection[] */
	private $classReflections = [];

	/** @var null|self */
	private static $instance;

	/** @var bool[] */
	private $hasClassCache;

	/** @var Parser */
	private $parser;

	public function __construct(Parser $parser)
	{
		$this->parser = $parser;

		self::$instance = $this;
	}

	public static function getInstance(): self
	{
		if (self::$instance === null) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		return self::$instance;
	}

	public function addClass(string $className, ClassReflection $classReflection): void
	{
		$this->classReflections[$className] = $classReflection;
	}

	public function getClass(string $className): ClassReflection
	{
		if (!$this->hasClass($className)) {
			throw new ClassNotFoundException($className);
//			return $this->classReflections[$className] = new ClassReflection($this, new ReflectionClass(\stdClass::class), null);
		}

		if (!isset($this->classReflections[$className])) {
			$reflectionClass = new ReflectionClass($className);
			$classReflection = $this->getClassFromReflection($reflectionClass);

			if ($className !== $reflectionClass->getName()) {
				// class alias optimization
				$this->classReflections[$reflectionClass->getName()] = $classReflection;
			}
		}

		return $this->classReflections[$className];
	}

	public function getClassFromReflection(\ReflectionClass $reflectionClass): ClassReflection
	{
		$className = $reflectionClass->getName();
		if (!isset($this->classReflections[$className])) {
			if ($reflectionClass->isUserDefined()) {
				// parsed file is traversed and class passed via $broker->addClass()
				$this->parser->parseFile($reflectionClass->getFileName());
			} else {
				$this->classReflections[$className] = new ClassReflection($this, $reflectionClass->getName(), $reflectionClass->getFileName() ?: 'native', null);
			}
		}

		return $this->classReflections[$className];
	}

	public function hasClass(string $className): bool
	{
		if (isset($this->hasClassCache[$className])) {
			return $this->hasClassCache[$className];
		}
		spl_autoload_register($autoloader = function (string $autoloadedClassName) use ($className): void {
			if ($autoloadedClassName !== $className && !$this->isExistsCheckCall()) {
				throw new \PHPStan\Broker\ClassAutoloadingException($autoloadedClassName);
			}
		});
		try {
			return $this->hasClassCache[$className] = class_exists($className) || interface_exists($className) || trait_exists($className);
		} catch (\PHPStan\Broker\ClassAutoloadingException $e) {
			throw $e;
		} catch (\Throwable $t) {
			throw new \PHPStan\Broker\ClassAutoloadingException(
				$className,
				$t
			);
		} finally {
			spl_autoload_unregister($autoloader);
		}
	}

	private function isExistsCheckCall(): bool
	{
		$debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$existsCallTypes = [
			'class_exists' => true,
			'interface_exists' => true,
			'trait_exists' => true,
		];
		foreach ($debugBacktrace as $traceStep) {
			if (
				isset($traceStep['function'])
				&& isset($existsCallTypes[$traceStep['function']])
				// We must ignore the self::hasClass calls
				&& (!isset($traceStep['file']) || $traceStep['file'] !== __FILE__)
			) {
				return true;
			}
		}
		return false;
	}

}
