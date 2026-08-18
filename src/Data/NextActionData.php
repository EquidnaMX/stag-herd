<?php

namespace Equidna\StagHerd\Data;

final readonly class NextActionData
{
    public const TYPE_NONE = 'none';

    public const TYPE_REDIRECT = 'redirect';

    public function __construct(
        public string $type = self::TYPE_NONE,
        public ?string $url = null,
        public array $data = [],
    ) {
        //
    }

    /**
     * Create an empty next action.
     */
    public static function none(): self
    {
        return new self(
            type: self::TYPE_NONE,
        );
    }

    /**
     * Create a redirect next action.
     */
    public static function redirect(string $url, array $data = []): self
    {
        return new self(
            type: self::TYPE_REDIRECT,
            url: $url,
            data: $data,
        );
    }

    /**
     * Check if the next action is a redirect.
     */
    public function isRedirect(): bool
    {
        return $this->type === self::TYPE_REDIRECT;
    }

    /**
     * Convert the next action to array.
     *
     * @return array{type: string, url: string|null, data: array}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'data' => $this->data,
        ];
    }
}
