<?php

namespace App\Data;

class MessageDeliveryResult
{
    /**
     * Create a new delivery result instance.
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * Create a successful delivery result.
     */
    public static function success(string $providerMessageId): self
    {
        return new self(
            successful: true,
            providerMessageId: $providerMessageId,
        );
    }

    /**
     * Create a failed delivery result.
     */
    public static function failure(string $error): self
    {
        return new self(
            successful: false,
            error: $error,
        );
    }
}
