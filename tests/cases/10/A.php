<?php declare(strict_types = 1);

class A
{

	/** @var B */
	private $inner;

	public function __construct(array $inner)
	{
		$this->inner = new B($inner);
	}

	/**
	 * @return mixed
	 */
	public function getSource(string $index)
	{
		return $this->inner->getSource($index);
	}

}
