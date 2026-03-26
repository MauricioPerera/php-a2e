<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

/**
 * Catalog of available operations.
 * Only registered operations can be executed — the security boundary.
 */
final class OperationRegistry
{
    /** @var array<string, OperationInterface> */
    private array $operations = [];

    public function register(OperationInterface $op): void
    {
        $this->operations[$op->type()] = $op;
    }

    public function get(string $type): ?OperationInterface
    {
        return $this->operations[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->operations[$type]);
    }

    /** @return string[] */
    public function types(): array
    {
        return array_keys($this->operations);
    }

    /**
     * Register all built-in operations.
     */
    public static function withDefaults(): self
    {
        $registry = new self();
        $registry->register(new ApiCall());
        $registry->register(new FilterData());
        $registry->register(new TransformData());
        $registry->register(new Conditional());
        $registry->register(new Loop());
        $registry->register(new StoreData());
        $registry->register(new Wait());
        $registry->register(new MergeData());
        $registry->register(new Calculate());
        $registry->register(new FormatText());
        $registry->register(new ExtractText());
        $registry->register(new ValidateData());
        $registry->register(new EncodeDecode());
        $registry->register(new GetCurrentDateTime());
        $registry->register(new ConvertTimezone());
        $registry->register(new DateCalculation());
        return $registry;
    }
}
