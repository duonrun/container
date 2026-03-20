<?php

declare(strict_types=1);

namespace Duon\Container;

use Closure;
use Duon\Container\Exception\ContainerException;
use Duon\Container\Exception\NotFoundException;
use Duon\Wire\CallableResolver;
use Duon\Wire\Creator;
use Duon\Wire\Exception\WireException;
use Duon\Wire\WireContainer;
use Override;
use Psr\Container\ContainerInterface as PsrContainer;

/**
 * @psalm-api
 *
 * @psalm-type EntryArray = array<never, never>|array<string, Entry>
 */
class Container implements WireContainer
{
	protected Creator $creator;
	protected readonly ?PsrContainer $wrappedContainer;
	protected bool $sealed = false;

	/** @psalm-var EntryArray */
	protected array $entries = [];

	/** @psalm-var array<never, never>|array<string, mixed> */
	protected array $instances = [];

	/** @psalm-var array<never, never>|array<non-empty-string, self> */
	protected array $tags = [];

	/** @psalm-var array<int, Resettable> */
	protected array $usedResettables = [];

	public function __construct(
		public readonly bool $autowire = true,
		?PsrContainer $container = null,
		protected readonly string $tag = '',
		protected readonly ?Container $parent = null,
		protected readonly bool $isScope = false,
	) {
		if ($container) {
			$this->wrappedContainer = $container;
			$this->add(PsrContainer::class, $container);
			$this->add($container::class, $container);
		} else {
			$this->wrappedContainer = null;
			$this->add(PsrContainer::class, $this);
		}
		$this->add(Container::class, $this);
		$this->creator = new Creator($this);
	}

	public function scope(): Container
	{
		$root = $this->root();

		if (!$root->sealed) {
			$root->seal();
		}

		return new self(
			autowire: $root->autowire,
			parent: $root,
			isScope: true,
		);
	}

	public function reset(): void
	{
		if (!$this->isScope) {
			return;
		}

		$resetIds = [];
		$this->resetScope($resetIds);
	}

	#[Override]
	public function has(string $id): bool
	{
		return isset($this->entries[$id]) || $this->parent?->has($id) || $this->wrappedContainer?->has($id);
	}

	/** @psalm-return list<string> */
	public function entries(bool $includeContainer = false): array
	{
		$keys = array_keys($this->entries);

		if ($includeContainer) {
			return $keys;
		}

		return array_values(array_filter($keys, function ($item) {
			return $item !== PsrContainer::class && !is_subclass_of($item, PsrContainer::class);
		}));
	}

	public function entry(string $id): Entry
	{
		return $this->entries[$id];
	}

	#[Override]
	public function get(string $id): mixed
	{
		try {
			if (array_key_exists($id, $this->instances)) {
				return $this->trackAndReturn($this->instances[$id]);
			}

			$resolved = $this->findEntry($id);

			if ($resolved !== null) {
				return $this->trackAndReturn(
					$this->resolveEntry(
						entryOwner: $resolved[0],
						entry: $resolved[1],
						id: $id,
						requester: $this,
					),
				);
			}

			$wrappedContainer = $this->root()->wrappedContainer;

			if ($wrappedContainer?->has($id)) {
				return $this->trackAndReturn($wrappedContainer->get($id));
			}

			// Autowiring: $id does not exists as an entry in the container
			if ($this->autowire && class_exists($id)) {
				return $this->trackAndReturn($this->creator->create($id));
			}
		} catch (WireException $e) {
			throw new NotFoundException('Unresolvable id: ' . $id . ' - Details: ' . $e->getMessage());
		}

		throw new NotFoundException('Unresolvable id: ' . $id);
	}

	#[Override]
	public function definition(string $id): mixed
	{
		$resolved = $this->findEntry($id);
		$entry = $resolved[1] ?? null;

		if ($entry !== null) {
			return $entry->definition();
		}

		throw new NotFoundException('Unresolvable definition - id: ' . $id);
	}

	/**
	 * @psalm-param non-empty-string $id
	 */
	public function add(
		string $id,
		mixed $value = null,
	): Entry {
		$this->assertMutable();
		$entry = new Entry($id, $value ?? $id);
		$this->entries[$id] = $entry;
		unset($this->instances[$id]);

		return $entry;
	}

	public function addEntry(
		Entry $entry,
	): Entry {
		$this->assertMutable();
		$this->entries[$entry->id] = $entry;
		unset($this->instances[$entry->id]);

		return $entry;
	}

	/** @psalm-param non-empty-string $tag */
	public function tag(string $tag): Container
	{
		if (isset($this->tags[$tag])) {
			return $this->tags[$tag];
		}

		if ($this->isRoot() && $this->sealed) {
			throw new ContainerException('The root container is sealed after scope() was called');
		}

		$parent = $this;
		$isScope = false;

		if ($this->isScope) {
			$root = $this->root();
			$parent = $root->tags[$tag] ?? $root;
			$isScope = true;
		}

		$this->tags[$tag] = new self(
			autowire: $this->autowire,
			tag: $tag,
			parent: $parent,
			isScope: $isScope,
		);

		return $this->tags[$tag];
	}

	public function new(string $id, mixed ...$args): object
	{
		$entry = $this->entries[$id] ?? null;

		if ($entry) {
			/** @var mixed */
			$value = $entry->definition();

			if (is_string($value)) {
				if (interface_exists($value)) {
					return $this->new($value, ...$args);
				}

				if (class_exists($value)) {
					/** @psalm-suppress MixedMethodCall */
					return new $value(...$args);
				}
			}
		}

		if (class_exists($id)) {
			/** @psalm-suppress MixedMethodCall */
			return new $id(...$args);
		}

		throw new NotFoundException('Cannot instantiate ' . $id);
	}

	protected function resolveEntry(Container $entryOwner, Entry $entry, string $id, Container $requester): mixed
	{
		if ($entry->shouldReturnValue()) {
			return $entry->definition();
		}

		[$cacheContainer, $resolutionContext] = $this->resolutionContainers($entry, $entryOwner, $requester);

		if ($cacheContainer !== null && array_key_exists($id, $cacheContainer->instances)) {
			return $cacheContainer->instances[$id];
		}

		$result = $this->materialize($entry, $resolutionContext);

		if ($cacheContainer !== null) {
			$cacheContainer->instances[$id] = $result;
		}

		return $result;
	}

	/**
	 * @return array{0: null|Container, 1: Container}
	 */
	protected function resolutionContainers(Entry $entry, Container $entryOwner, Container $requester): array
	{
		return match ($entry->getLifetime()) {
			Lifetime::Shared => [$entryOwner, $entryOwner],
			Lifetime::Scoped => [$requester, $requester],
			Lifetime::Transient => [null, $requester],
		};
	}

	protected function materialize(Entry $entry, Container $context): mixed
	{
		/** @var mixed - the current value, instantiated or definition */
		$value = $entry->definition();

		if (is_string($value)) {
			if (class_exists($value)) {
				$constructor = $entry->getConstructor();
				$args = $entry->getArgs();

				if (isset($args)) {
					// Don't autowire if $args are given
					if ($args instanceof Closure) {
						/** @psalm-var array<string, mixed> */
						$args = $args(...(new CallableResolver($context->creator))->resolve($args));

						return $this->applyCalls($entry, $context->creator->create($value, $args), $context);
					}

					return $this->applyCalls(
						$entry,
						$context->creator->create(
							$value,
							predefinedArgs: $args,
							constructor: $constructor ?? '',
						),
						$context,
					);
				}

				return $this->applyCalls(
					$entry,
					$context->creator->create($value, constructor: $constructor ?? ''),
					$context,
				);
			}

			if ($context->has($value)) {
				return $context->get($value);
			}
		}

		if ($value instanceof Closure) {
			$args = $entry->getArgs();

			if (is_null($args)) {
				$args = (new CallableResolver($context->creator))->resolve($value);
			} elseif ($args instanceof Closure) {
				/** @var array<string, mixed> */
				$args = $args();
			}

			/** @var mixed */
			$result = $value(...$args);

			return $this->applyCalls($entry, $result, $context);
		}

		if (is_object($value)) {
			return $value;
		}

		throw new NotFoundException('Unresolvable id: ' . (string) $value);
	}

	protected function applyCalls(Entry $entry, mixed $value, Container $context): mixed
	{
		foreach ($entry->getCalls() as $call) {
			$methodToResolve = $call->method;

			/** @psalm-var callable */
			$callable = [$value, $methodToResolve];
			$args = (new CallableResolver($context->creator))->resolve($callable, $call->args);
			$callable(...$args);
		}

		return $value;
	}

	/**
	 * @param array<int, true> $resetIds
	 */
	protected function resetScope(array &$resetIds): void
	{
		foreach ($this->instances as $instance) {
			$this->resetIfNeeded($instance, $resetIds);
		}

		foreach ($this->usedResettables as $usedResettable) {
			$this->resetIfNeeded($usedResettable, $resetIds);
		}

		foreach ($this->tags as $tagContainer) {
			if ($tagContainer->isScope) {
				$tagContainer->resetScope($resetIds);
			}
		}

		$this->instances = [];
		$this->entries = [];
		$this->tags = [];
		$this->usedResettables = [];
		$this->add(PsrContainer::class, $this);
		$this->add(Container::class, $this);
	}

	/**
	 * @param array<int, true> $resetIds
	 */
	protected function resetIfNeeded(mixed $value, array &$resetIds): void
	{
		if (!$value instanceof Resettable) {
			return;
		}

		$objectId = spl_object_id($value);

		if (isset($resetIds[$objectId])) {
			return;
		}

		$resetIds[$objectId] = true;
		$value->reset();
	}

	protected function trackAndReturn(mixed $value): mixed
	{
		if ($this->isScope && $value instanceof Resettable) {
			$this->usedResettables[spl_object_id($value)] = $value;
		}

		return $value;
	}

	/** @return array{Container, Entry}|null */
	protected function findEntry(string $id): ?array
	{
		$entry = $this->entries[$id] ?? null;

		if ($entry !== null) {
			return [$this, $entry];
		}

		return $this->parent?->findEntry($id);
	}

	protected function root(): Container
	{
		$container = $this;

		while ($container->parent !== null) {
			$container = $container->parent;
		}

		return $container;
	}

	protected function assertMutable(): void
	{
		if ($this->sealed) {
			throw new ContainerException('The root container is sealed after scope() was called');
		}
	}

	protected function seal(): void
	{
		$this->sealed = true;

		foreach ($this->tags as $tagContainer) {
			$tagContainer->seal();
		}
	}

	protected function isRoot(): bool
	{
		return $this->parent === null && $this->tag === '' && !$this->isScope;
	}
}
