<?php

declare(strict_types=1);

namespace Celemas\Container\Tests;

use Celemas\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TestCase extends BaseTestCase
{
	public function container(
		bool $autowire = true,
	): Container {
		$container = new Container(autowire: $autowire);
		$container->add(Container::class, $container);

		return $container;
	}

	public function throws(string $exception, ?string $message = null): void
	{
		$this->expectException($exception);

		if ($message) {
			$this->expectExceptionMessage($message);
		}
	}
}
