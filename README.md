# Duon Container

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/1c88bda8fa4c4b56897fd8930f42b1a1)](https://app.codacy.com/gh/duonrun/container/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/1c88bda8fa4c4b56897fd8930f42b1a1)](https://app.codacy.com/gh/duonrun/container/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
[![Psalm coverage](https://shepherd.dev/github/duonrun/container/coverage.svg?)](https://shepherd.dev/github/duonrun/container)
[![Psalm level](https://shepherd.dev/github/duonrun/container/level.svg?)](https://duonrun.dev/container)

A PSR-11 compatible dependency injection container.

## Entry lifetimes

Container entries use explicit lifetimes:

- `shared()` caches one instance per container (default for added entries)
- `scoped()` caches one instance per requesting container scope
- `transient()` never caches
- `value()` returns the configured definition as-is (for literals or raw closures)

```php
$container->add('config', ['debug' => true])->value();
$container->add(Service::class)->shared();
$container->add(RequestContext::class)->scoped();
$container->add(Builder::class)->transient();
```

## License

This project is licensed under the [MIT license](LICENSE.md).
