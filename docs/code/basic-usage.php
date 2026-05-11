<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Celemas\Container\Container;

class Value
{
    public function get(): string
    {
        return 'string';
    }
}

$container = new Container();
$container->add(Value::class);

$value = $container->get(Value::class);

assert($value instanceof Value);
assert($value->get() === 'string');
