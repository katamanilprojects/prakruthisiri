<?php

declare(strict_types=1);

namespace PrakruthiSiri\Exceptions;

use RuntimeException;

/**
 * Thrown when an order item fails the atomic inventory decrement check.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        private readonly int $productId,
        private readonly string $productName,
        private readonly int $requestedQuantity,
        private readonly int $availableStock,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === '') {
            $message = sprintf(
                "Insufficient stock for '%s' (Product ID: %d). Requested: %d packet(s) [0.5 kg each], Available: %d packet(s).",
                $this->productName,
                $this->productId,
                $this->requestedQuantity,
                $this->availableStock
            );
        }

        parent::__construct($message, $code, $previous);
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }

    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }
}
