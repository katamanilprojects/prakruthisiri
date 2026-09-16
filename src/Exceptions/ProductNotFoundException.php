<?php

declare(strict_types=1);

namespace PrakruthiSiri\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested product cannot be resolved in the catalog or is inactive.
 */
class ProductNotFoundException extends RuntimeException
{
    public function __construct(
        private readonly int $productId,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === '') {
            $message = sprintf("Product with ID %d was not found or is currently inactive.", $this->productId);
        }

        parent::__construct($message, $code, $previous);
    }

    public function getProductId(): int
    {
        return $this->productId;
    }
}
