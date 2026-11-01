<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use RuntimeException;

/** A change references a parent row that has not been synchronised yet. */
class MissingParent extends RuntimeException
{
}
