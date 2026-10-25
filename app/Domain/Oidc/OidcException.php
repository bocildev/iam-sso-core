<?php

namespace App\Domain\Oidc;

use RuntimeException;

class OidcException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description = '',
        public readonly int $status = 400,
    ) {
        parent::__construct($error.($description ? ': '.$description : ''));
    }

    public ?string $redirectTo = null;

    public ?string $state = null;

    public function withRedirect(string $uri, ?string $state): static
    {
        $this->redirectTo = $uri;
        $this->state = $state;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter(['error' => $this->error, 'error_description' => $this->description]);
    }
}

