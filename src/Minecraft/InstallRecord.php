<?php

namespace Wyvern\Minecraft;

/** What the egg's install script actually resolved, from .wyvern/install.json. */
final readonly class InstallRecord
{
    public function __construct(
        public ?Loader $loader,
        public ?string $minecraft,
        public ?string $build,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Loader::tryFrom((string) ($data['loader'] ?? '')),
            self::value($data['minecraft'] ?? null),
            self::value($data['build'] ?? null),
        );
    }

    private static function value(mixed $value): ?string
    {
        return is_string($value) && $value !== '' && $value !== 'latest' ? $value : null;
    }
}
