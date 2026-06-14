<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * The result of one invoice-conformity check (a single mandatory mention, or a
 * single coherence rule) — with a short narrative explaining how the control was
 * performed and, when it fails, the origin of the variance.
 */
final readonly class ConformityFinding
{
    public function __construct(
        public ConformityType $type,
        public string $key,
        public bool $passed,
        public ?string $detail = null,
    ) {}

    public function label(): string
    {
        return (string) __('app.conformity.'.$this->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'key' => $this->key,
            'label' => $this->label(),
            'passed' => $this->passed,
            'detail' => $this->detail,
        ];
    }
}
