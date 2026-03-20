<?php

declare(strict_types=1);

namespace Duon\Container\Tests;

use Closure;
use Duon\Container\Container;
use Duon\Container\Entry;
use Duon\Container\Exception\ContainerException;
use Duon\Container\Exception\NotFoundException;
use Duon\Container\Tests\Fixtures\TestClass;
use Duon\Container\Tests\Fixtures\TestClassApp;
use Duon\Container\Tests\Fixtures\TestClassContainerArgs;
use Duon\Container\Tests\Fixtures\TestClassContainerSingleArg;
use Duon\Container\Tests\Fixtures\TestClassWithConstructor;
use Duon\Container\Tests\Fixtures\TestContainer;
use Duon\Container\Tests\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

final class ContainerTest extends TestCase
{
	public function testAddKeyWithoutValue(): void
	{
		$container = new Container();
		$container->add('value');

		$this->assertSame('value', $container->entry('value')->definition());
	}

	public function testAddEntryInstance(): void
	{
		$container = new Container();
		$container->addEntry(new Entry('key', 'value'));

		$this->assertSame('value', $container->entry('key')->definition());
	}

	public function testEntryInstanceAndValue(): void
	{
		$container = new Container();
		$container->add(stdClass::class);

		$this->assertSame(stdClass::class, $container->entry(stdClass::class)->definition());

		$obj1 = $container->get(stdClass::class);
		$obj2 = $container->get(stdClass::class);

		$this->assertSame(true, $obj1 instanceof stdClass);
		$this->assertSame(stdClass::class, $container->entry(stdClass::class)->definition());
		$this->assertSame($obj1, $obj2);
	}

	public function testCheckIfRegistered(): void
	{
		$container = new Container();
		$container->add('container', $container);

		$this->assertSame(true, $container->has('container'));
		$this->assertSame(false, $container->has('wrong'));
	}

	public function testCheckIfRegisteredOnParentFromTag(): void
	{
		$container = new Container();
		$container->add('container', $container);
		$tag = $container->tag('test');

		$this->assertSame(true, $tag->has('container'));
		$this->assertSame(false, $tag->has('wrong'));
	}

	public function testInstantiate(): void
	{
		$container = new Container();
		$container->add('container', Container::class);
		$container->add('test', TestClass::class);
		$con = $container->new('container');
		$test = $container->new('test');

		$this->assertSame(true, $con instanceof Container);
		$this->assertSame(true, $test instanceof TestClass);
	}

	public function testInstantiateWithCall(): void
	{
		$container = new Container();
		$container->add(TestClass::class)->call('init', value: 'testvalue');
		$tc = $container->get(TestClass::class);

		$this->assertSame(true, $tc instanceof TestClass);
		$this->assertSame('testvalue', $tc->value);
	}

	public function testChainedInstantiation(): void
	{
		$container = new Container();
		$container->add(
			\Psr\Container\ContainerExceptionInterface::class,
			\Psr\Container\NotFoundExceptionInterface::class,
		);
		$container->add(
			\Psr\Container\NotFoundExceptionInterface::class,
			NotFoundException::class,
		);
		$exception = $container->new(
			\Psr\Container\ContainerExceptionInterface::class,
			'The message',
			13,
		);

		$this->assertSame(true, $exception instanceof NotFoundException);
		$this->assertSame('The message', $exception->getMessage());
		$this->assertSame(13, $exception->getCode());
	}

	public function testFactoryMethodInstantiation(): void
	{
		$container = new Container();
		$container->add(TestClassContainerArgs::class)->constructor('fromDefaults');
		$instance = $container->get(TestClassContainerArgs::class);

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('fromDefaults', $instance->app->app());
		$this->assertSame('fromDefaults', $instance->test);
	}

	public function testFactoryMethodInstantiationWithArgs(): void
	{
		$container = new Container();
		$container
			->add(TestClassContainerArgs::class)
			->constructor('fromArgs')
			->args(test: 'passed', app: 'passed');
		$instance = $container->get(TestClassContainerArgs::class);

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('passed', $instance->app->app());
		$this->assertSame('passed', $instance->test);
	}

	public function testAutowiredInstantiation(): void
	{
		$container = new Container();

		$this->assertSame(true, $container->new(NotFoundException::class) instanceof NotFoundException);
	}

	public function testAutowiredInstantiationFails(): void
	{
		$this->throws(NotFoundException::class, 'Cannot instantiate Duon\Container\Tests\NoValidClass');

		$container = new Container();

		$this->assertSame(true, $container->new(NoValidClass::class) instanceof NotFoundException);
	}

	public function testResolveInstance(): void
	{
		$container = new Container();
		$object = new stdClass();
		$container->add('object', $object);

		$this->assertSame($object, $container->get('object'));
	}

	public function testResolveSimpleClass(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class);

		$this->assertSame(true, $container->get('class') instanceof stdClass);
	}

	public function testResolveChainedEntry(): void
	{
		$container = new Container();
		$container->add(
			Psr\Container\ContainerExceptionInterface::class,
			Psr\Container\NotFoundExceptionInterface::class,
		);
		$container->add(
			Psr\Container\NotFoundExceptionInterface::class,
			NotFoundException::class,
		);

		$this->assertSame(
			true,
			$container->get(Psr\Container\ContainerExceptionInterface::class) instanceof NotFoundException,
		);
	}

	public function testResolveClassWithConstructor(): void
	{
		$container = new Container();

		$object = $container->get(TestClassWithConstructor::class);

		$this->assertSame($object::class, TestClassWithConstructor::class);
		$this->assertSame(TestClass::class, $object->tc::class);
	}

	public function testResolveClosureClass(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add('class', function (TestClassApp $app) {
			return new TestClassContainerArgs(new TestClass(), 'chuck', $app);
		});
		$instance = $container->get('class');

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testDefinition(): void
	{
		$container = new Container();
		$container->add('container', Container::class);

		$this->assertSame(Container::class, $container->definition('container'));
	}

	public function testDefinitionOnTagCanResolveParentEntry(): void
	{
		$container = new Container();
		$container->add('container', Container::class);

		$this->assertSame(Container::class, $container->tag('api')->definition('container'));
	}

	public function testFailingDefinition(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable');

		$container = new Container();
		$container->definition('container');
	}

	public function testRejectUnresolvableClass(): void
	{
		$this->throws(ContainerException::class, 'Unresolvable');

		$container = new Container();
		$container->get(GdImage::class);
	}

	public function testGettingNonExistentClassFails(): void
	{
		$this->throws(NotFoundException::class, 'NonExistent');

		$container = new Container();
		$container->get('NonExistent');
	}

	public function testGettingNonResolvableEntryFails(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable id: Duon\Container\Tests\InvalidClass');

		$container = new Container();
		$container->add('unresolvable', InvalidClass::class);
		$container->get('unresolvable');
	}

	public function testGettingNonResolvableAutowiringFails(): void
	{
		$this->throws(
			NotFoundException::class,
			'Unresolvable id: Duon\Container\Tests\Fixtures\TestClassContainerArgs',
		);

		$container = new Container(autowire: true);
		$container->get(TestClassContainerArgs::class);
	}

	public function testRejectingClassWithNonResolvableParams(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable:');

		$container = new Container();
		$container->add('unresolvable', TestClassContainerArgs::class);
		$container->get('unresolvable');
	}

	public function testResolveWithArgsArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerArgs::class)->args([
			'test' => 'chuck',
			'tc' => new TestClass(),
		]);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithSingleNamedArgArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerSingleArg::class)->args(
			test: 'chuck',
		);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerSingleArg);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithNamedArgsArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerArgs::class)->args(
			test: 'chuck',
			tc: new TestClass(),
		);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveClosureClassWithArgs(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add('class', function (TestClassApp $app, string $name, TestClass $tc) {
			return new TestClassContainerArgs($tc, $name, $app);
		})->args(app: new TestClassApp('chuck'), tc: new TestClass(), name: 'chuck');
		$instance = $container->get('class');

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithArgsClosure(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add('class', TestClassContainerArgs::class)->args(function (TestClassApp $app) {
			return [
				'test' => 'chuck',
				'tc' => new TestClass(),
				'app' => $app,
			];
		});
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveClosureClassWithArgsClosure(): void
	{
		$container = new Container();
		$container->add('class', function (TestClassApp $app, string $name, TestClass $tc) {
			return new TestClassContainerArgs($tc, $name, $app);
		})->args(function () {
			return [
				'app' => new TestClassApp('chuck'),
				'tc' => new TestClass(),
				'name' => 'chuck',
			];
		});
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testRejectMultipleUnnamedArgs(): void
	{
		$this->throws(ContainerException::class, 'Container entry arguments');

		$container = new Container();
		$container->add('class', function () {
			return new stdClass();
		})->args('chuck', 13);
	}

	public function testRejectSingleUnnamedArgWithWrongType(): void
	{
		$this->throws(ContainerException::class, 'Container entry arguments');

		$container = new Container();
		$container->add('class', function () {
			return new stdClass();
		})->args('chuck');
	}

	public function testSharedLifetimeCachesObjectsByDefault(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class);
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame($obj1 === $obj2, true);
	}

	public function testValue(): void
	{
		$container = new Container();
		$container->add('closure1', fn() => 'called');
		$container->add('closure2', fn() => 'notcalled')->value();
		$value1 = $container->get('closure1');
		$value2 = $container->get('closure2');

		$this->assertSame('called', $value1);
		$this->assertSame(true, $value2 instanceof Closure);
	}

	public function testTransientLifetimeReturnsFreshInstances(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->transient();
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame(false, $obj1 === $obj2);
	}

	public function testScopedLifetimeCachesObjectsInSameContainer(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->scoped();
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame(true, $obj1 === $obj2);
	}

	public function testAutowiringUsesTransientLifetime(): void
	{
		$container = new Container();
		$obj1 = $container->get(stdClass::class);
		$obj2 = $container->get(stdClass::class);

		$this->assertSame(false, $obj1 === $obj2);
	}

	public function testScopeFreezesRoot(): void
	{
		$this->throws(ContainerException::class, 'frozen');

		$container = new Container();
		$container->scope();
		$container->add('new-entry', stdClass::class);
	}

	public function testScopeHasOwnLocalEntries(): void
	{
		$container = new Container();
		$scope = $container->scope();
		$scope->add('request', new stdClass());

		$this->assertSame(true, $scope->has('request'));
		$this->assertSame(false, $container->has('request'));
	}

	public function testScopeStartsEmptyEveryTime(): void
	{
		$container = new Container();
		$scope1 = $container->scope();
		$scope1->add('request', new stdClass());
		$scope2 = $container->scope();

		$this->assertSame(true, $scope1->has('request'));
		$this->assertSame(false, $scope2->has('request'));
	}

	public function testScopeRebindsContainerEntriesToItself(): void
	{
		$container = new Container();
		$scope = $container->scope();

		$this->assertSame($container, $container->get(Container::class));
		$this->assertSame($container, $container->get(ContainerInterface::class));
		$this->assertSame($scope, $scope->get(Container::class));
		$this->assertSame($scope, $scope->get(ContainerInterface::class));
	}

	public function testRootSharedLifetimeIsReusedAcrossScopes(): void
	{
		$container = new Container();
		$container->add('service', fn() => new stdClass())->shared();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service1 = $scope1->get('service');
		$service2 = $scope2->get('service');

		$this->assertSame(true, $service1 === $service2);
	}

	public function testRootScopedLifetimeCreatesOneInstancePerScope(): void
	{
		$container = new Container();
		$container->add('service', fn() => new stdClass())->scoped();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service11 = $scope1->get('service');
		$service12 = $scope1->get('service');
		$service2 = $scope2->get('service');

		$this->assertSame(true, $service11 === $service12);
		$this->assertSame(false, $service11 === $service2);
	}

	public function testRootTransientLifetimeStaysTransientInScope(): void
	{
		$container = new Container();
		$container->add('service', fn() => new stdClass())->transient();
		$scope = $container->scope();
		$service1 = $scope->get('service');
		$service2 = $scope->get('service');

		$this->assertSame(false, $service1 === $service2);
	}

	public function testSharedServicesResolveInOwnerContext(): void
	{
		$container = new Container();
		$container->add('name', 'root')->value();
		$container->add('service', fn(Container $resolvedContainer): string => $resolvedContainer->get('name'));
		$scope = $container->scope();
		$scope->add('name', 'scope')->value();

		$this->assertSame('root', $scope->get('service'));
	}

	public function testScopedServicesResolveInRequesterContext(): void
	{
		$container = new Container();
		$container->add('name', 'root')->value();
		$container
			->add('service', fn(Container $resolvedContainer): string => $resolvedContainer->get('name'))
			->scoped();
		$scope = $container->scope();
		$scope->add('name', 'scope')->value();

		$this->assertSame('scope', $scope->get('service'));
	}

	public function testFetchEntriesList(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->transient();

		$this->assertSame(['class'], $container->entries());
		$this->assertSame(
			['Psr\Container\ContainerInterface', 'Duon\Container\Container', 'class'],
			$container->entries(includeContainer: true),
		);
	}

	public function testAddAndReceiveTaggedEntries(): void
	{
		$container = new Container();
		$container->tag('tag')->add('class', stdClass::class);
		$container->tag('tag')->add('container', Container::class);
		$obj = $container->tag('tag')->get('class');
		$entry = $container->tag('tag')->entry('class');
		$entryCon = $container->tag('tag')->entry('container');

		$this->assertSame(['class', 'container'], $container->tag('tag')->entries());
		$this->assertSame([
			'Psr\Container\ContainerInterface',
			'Duon\Container\Container',
			'class',
			'container',
		], $container->tag('tag')->entries(true));
		$this->assertSame(true, $obj instanceof stdClass);
		$this->assertSame(stdClass::class, $entry->definition());
		$this->assertSame(Container::class, $entryCon->definition());
		$this->assertSame(true, $obj === $container->tag('tag')->get('class'));
		$this->assertSame(true, $container->tag('tag')->has('class'));
		$this->assertSame(true, $container->tag('tag')->has('container'));
		$this->assertSame(false, $container->tag('tag')->has('wrong'));
		$this->assertSame(false, $container->has('class'));
		$this->assertSame(false, $container->has('container'));
	}

	public function testAddTaggedKeyWithoutValue(): void
	{
		$container = new Container();
		$container->tag('tag')->add(TestClassApp::class);

		$this->assertSame(TestClassApp::class, $container->tag('tag')->entry(TestClassApp::class)->definition());
	}

	public function testRootTagCreationFailsAfterFirstScope(): void
	{
		$this->throws(ContainerException::class, 'frozen');

		$container = new Container();
		$container->scope();
		$container->tag('new-tag');
	}

	public function testScopeTagInheritsRootTagDefinitions(): void
	{
		$container = new Container();
		$container->tag('api')->add('service', fn() => new stdClass())->scoped();
		$scope = $container->scope();
		$tag = $scope->tag('api');
		$service1 = $tag->get('service');
		$service2 = $tag->get('service');

		$this->assertSame(true, $service1 instanceof stdClass);
		$this->assertSame(true, $service1 === $service2);
	}

	public function testScopeTagsKeepOwnScopedCaches(): void
	{
		$container = new Container();
		$container->tag('api')->add('service', fn() => new stdClass())->scoped();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service1 = $scope1->tag('api')->get('service');
		$service2 = $scope2->tag('api')->get('service');

		$this->assertSame(false, $service1 === $service2);
	}

	public function testThirdPartyContainer(): void
	{
		$testContainer = new TestContainer();
		$testContainer->add('external', new stdClass());
		$container = new Container(container: $testContainer);
		$container->add('internal', new Container());

		$this->assertSame(true, $container->get('external') instanceof stdClass);
		$this->assertSame(true, $container->get('internal') instanceof Container);
		$this->assertSame(true, $container->get(ContainerInterface::class) instanceof TestContainer);
		$this->assertSame($testContainer, $container->get(ContainerInterface::class));
		$this->assertSame($testContainer, $container->get(TestContainer::class));
	}

	public function testScopeResolvesWrappedEntriesViaRoot(): void
	{
		$testContainer = new TestContainer();
		$external = new stdClass();
		$testContainer->add('external', $external);
		$container = new Container(container: $testContainer);
		$scope = $container->scope();

		$this->assertSame($external, $scope->get('external'));
	}

	public function testScopePrefersParentEntriesOverWrappedContainer(): void
	{
		$testContainer = new TestContainer();
		$testContainer->add('shared-key', 'wrapped');
		$container = new Container(container: $testContainer);
		$container->add('shared-key', 'root')->value();
		$scope = $container->scope();

		$this->assertSame('root', $scope->get('shared-key'));
	}

	public function testGettingNonExistentTaggedEntryFails(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable id: NonExistent');

		$container = new Container();

		$container->tag('tag')->get('NonExistent');
	}
}
