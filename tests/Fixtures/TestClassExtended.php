<?php

declare(strict_types=1);

namespace Duon\Registry\Tests\Fixtures;

class TestClassExtended extends TestClass
{
	public function __toString(): string
	{
		return 'Stringable extended';
	}
}
