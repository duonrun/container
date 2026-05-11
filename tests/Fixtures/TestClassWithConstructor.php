<?php

declare(strict_types=1);

namespace Celemas\Container\Tests\Fixtures;

class TestClassWithConstructor
{
	public function __construct(
		public readonly TestClass $tc,
	) {}
}
