<?php

declare(strict_types=1);

namespace Duon\Container;

enum Lifetime
{
	case Shared;
	case Scoped;
	case Transient;
}
