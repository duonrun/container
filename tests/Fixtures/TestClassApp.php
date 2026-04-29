<?php

declare(strict_types=1);

namespace Duon\Container\Tests\Fixtures;

class TestClassApp
{
	public function __construct(
		public readonly string $app = 'chuck',
		public readonly bool $debug = false,
		public readonly string $env = '',
	) {}

	public function app(): string
	{
		return $this->app;
	}

	public function debug(): bool
	{
		return $this->debug;
	}

	public function env(): string
	{
		return $this->env;
	}
}
