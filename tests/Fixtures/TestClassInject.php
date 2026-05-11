<?php

declare(strict_types=1);

namespace Celemas\Container\Tests\Fixtures;

use Celemas\Container\Container;
use Celemas\Wire\Call;
use Celemas\Wire\Inject;

#[Call('callThis')]
class TestClassInject
{
	public ?Container $container = null;
	public ?TestClassApp $app = null;
	public ?TestClass $tc = null;
	public string $arg1 = '';
	public int $arg2 = 0;
	public string $calledArg1 = '';
	public int $calledArg2 = 0;

	#[Inject(arg2: 13, tc: TestClassExtended::class), Inject(app: 'injected', arg1: 'arg1')]
	public function __construct(
		string $arg1,
		Container $container,
		TestClassApp $app,
		int $arg2,
		TestClass $tc,
	) {
		$this->container = $container;
		$this->app = $app;
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
		$this->tc = $tc;
	}

	#[Inject(calledArg2: 73, calledArg1: 'calledArg1')]
	public function callThis(string $calledArg1, int $calledArg2): void
	{
		$this->calledArg1 = $calledArg1;
		$this->calledArg2 = $calledArg2;
	}
}
