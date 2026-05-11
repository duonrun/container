<?php

declare(strict_types=1);

namespace Celemas\Container\Tests\Fixtures;

use Celemas\Container\Resettable;

final class ResettableService implements Resettable
{
	public int $resetCalls = 0;

	public function reset(): void
	{
		++$this->resetCalls;
	}
}
