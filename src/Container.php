<?php

declare(strict_types=1);

namespace Duon\Container;

use Closure;
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

	/** @psalm-var EntryArray */
	protected array $entries = [];

	/** @psalm-var array<never, never>|array<string, mixed> */
	protected array $instances = [];

	/** @psalm-var array<never, never>|array<non-empty-string, self> */
	protected array $tags = [];

	public function __construct(
		public readonly bool $autowire = true,
		?PsrContainer $container = null,
		protected readonly string $tag = '',
		protected readonly ?Container $parent = null,
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
				return $this->instances[$id];
			}

			$entry = $this->entries[$id] ?? null;

			if ($entry) {
				return $this->resolveEntry($entry, $id);
			}

			if ($this->wrappedContainer?->has($id)) {
				return $this->wrappedContainer->get($id);
			}

			// We are in a tag. See if the $id can be resolved by the parent
			// be registered on the root.
			if ($this->parent) {
				return $this->parent->get($id);
			}

			// Autowiring: $id does not exists as an entry in the container
			if ($this->autowire && class_exists($id)) {
				return $this->creator->create($id);
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
		$entry = new Entry($id, $value ?? $id);
		$this->entries[$id] = $entry;
		unset($this->instances[$id]);

		return $entry;
	}

	public function addEntry(
		Entry $entry,
	): Entry {
		$this->entries[$entry->id] = $entry;
		unset($this->instances[$entry->id]);

		return $entry;
	}

	/** @psalm-param non-empty-string $tag */
	public function tag(string $tag): Container
	{
		if (!isset($this->tags[$tag])) {
			$this->tags[$tag] = new self(tag: $tag, parent: $this);
		}

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

	protected function callAndCache(Entry $entry, mixed $value, string $id): mixed
	{
		foreach ($entry->getCalls() as $call) {
			$methodToResolve = $call->method;

			/** @psalm-var callable */
			$callable = [$value, $methodToResolve];
			$args = (new CallableResolver($this->creator))->resolve($callable, $call->args);
			$callable(...$args);
		}

		if ($entry->getLifetime() !== Lifetime::Transient) {
			$this->instances[$id] = $value;
		}

		return $value;
	}

	protected function resolveEntry(Entry $entry, string $id): mixed
	{
		if ($entry->shouldReturnValue()) {
			return $entry->definition();
		}

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
						$args = $args(...(new CallableResolver($this->creator))->resolve($args));

						return $this->callAndCache(
							$entry,
							$this->creator->create($value, $args),
							$id,
						);
					}

					return $this->callAndCache(
						$entry,
						$this->creator->create(
							$value,
							predefinedArgs: $args,
							constructor: $constructor ?? '',
						),
						$id,
					);
				}

				return $this->callAndCache(
					$entry,
					$this->creator->create($value, constructor: $constructor ?? ''),
					$id,
				);
			}

			if ($this->has($value)) {
				$result = $this->get($value);

				if ($entry->getLifetime() !== Lifetime::Transient) {
					$this->instances[$id] = $result;
				}

				return $result;
			}
		}

		if ($value instanceof Closure) {
			$args = $entry->getArgs();

			if (is_null($args)) {
				$args = (new CallableResolver($this->creator))->resolve($value);
			} elseif ($args instanceof Closure) {
				/** @var array<string, mixed> */
				$args = $args();
			}

			/** @var mixed */
			$result = $value(...$args);

			return $this->callAndCache($entry, $result, $id);
		}

		if (is_object($value)) {
			return $value;
		}

		throw new NotFoundException('Unresolvable id: ' . (string) $value);
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
}
