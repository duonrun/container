<?php

declare(strict_types=1);

namespace Celemas\Container;

interface Resettable
{
	public function reset(): void;
}
