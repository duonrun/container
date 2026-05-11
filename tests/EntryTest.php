<?php

declare(strict_types=1);

namespace Celemas\Container\Tests;

use Celemas\Container\Entry;
use Celemas\Container\Lifetime;
use stdClass;

final class EntryTest extends TestCase
{
	public function testEntryMethods(): void
	{
		$entry = new Entry('key', stdClass::class);

		$this->assertSame(stdClass::class, $entry->definition());
		$this->assertSame(null, $entry->getConstructor());
		$this->assertSame(Lifetime::Shared, $entry->getLifetime());
		$this->assertSame(false, $entry->shouldReturnValue());
		$this->assertSame(null, $entry->getArgs());

		$entry
			->constructor('factoryMethod')
			->transient()
			->value(true)
			->args(arg1: 13, arg2: 'test');

		$this->assertSame(stdClass::class, $entry->definition());
		$this->assertSame('factoryMethod', $entry->getConstructor());
		$this->assertSame(Lifetime::Transient, $entry->getLifetime());
		$this->assertSame(true, $entry->shouldReturnValue());
		$this->assertSame(['arg1' => 13, 'arg2' => 'test'], $entry->getArgs());
	}

	public function testEntryCallMethod(): void
	{
		$entry = new Entry('key', stdClass::class);
		$entry->call('method', arg1: 13, arg2: 'arg2');
		$entry->call('next');

		$call1 = $entry->getCalls()[0];
		$call2 = $entry->getCalls()[1];

		$this->assertSame('method', $call1->method);
		$this->assertSame(['arg1' => 13, 'arg2' => 'arg2'], $call1->args);
		$this->assertSame('next', $call2->method);
		$this->assertSame([], $call2->args);
	}

	public function testLifetimeHelpers(): void
	{
		$entry = new Entry('key', stdClass::class);
		$this->assertSame(Lifetime::Shared, $entry->shared()->getLifetime());
		$this->assertSame(Lifetime::Scoped, $entry->scoped()->getLifetime());
		$this->assertSame(Lifetime::Transient, $entry->transient()->getLifetime());
		$this->assertSame(Lifetime::Shared, $entry->lifetime(Lifetime::Shared)->getLifetime());
	}

	public function testLegacyEntryApiWasRemoved(): void
	{
		$this->assertSame(false, method_exists(Entry::class, 'reify'));
		$this->assertSame(false, method_exists(Entry::class, 'asIs'));
	}
}
