<?php

declare(strict_types=1);

namespace Celemas\Container;

enum Lifetime
{
	case Shared;
	case Scoped;
	case Transient;
}
