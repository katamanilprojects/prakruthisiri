<?php

declare(strict_types=1);

namespace PrakruthiSiri\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when order inputs, cart data, or customer attributes fail domain validation.
 */
class OrderValidationException extends InvalidArgumentException
{
}
