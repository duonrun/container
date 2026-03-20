# Changelog

## [Unreleased]

### Changed

- `Container` now keeps runtime instances in container-local caches instead of on `Entry` objects.
- `Container::definition()` now resolves definitions through parent containers (for example from tags).
- `Entry` now uses explicit lifetimes with `shared()`, `scoped()`, `transient()`, and `lifetime(...)`.
- `Entry::asIs()` was renamed to `Entry::value()`.
- `Entry::reify()` was removed.
- Added `Container::scope()` for isolated per-unit-of-work containers.
- The root container now freezes internal structural mutation after the first `scope()` call.
- Shared entries resolve in definition-owner context, scoped and transient entries resolve in requester context.
- Scope tags now layer over matching root tags and keep scope-local caches.
- Wrapped PSR container fallback now routes through the root container in scoped resolution.

## [0.2.0](https://github.com/duonrun/container/releases/tag/0.2.0) (2026-02-21)

Project renamed from duon/registry to duon/container.

Codename: Jonas

### Changed

- Package renamed from `duon/registry` to `duon/container`
- Namespace changed from `Duon\Registry` to `Duon\Container`
- Main class renamed from `Registry` to `Container`
- Parameter `includeRegistry` renamed to `includeContainer`

## [0.1.0](https://github.com/duonrun/registry/releases/tag/0.1.0) (2026-01-30)

Initial release.

### Added

- PSR-11 compatible dependency injection container
- Service registration and resolution
- Autowiring support via duon/wire integration
