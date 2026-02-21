<?php

declare(strict_types=1);

namespace Duon\Container\Tests\Fixtures;

class TestClassResolver
{
	public function __construct(
		public readonly string $name,
		public readonly TestClass $tc,
		public readonly int $number,
	) {}
}
