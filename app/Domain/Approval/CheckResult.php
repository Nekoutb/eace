<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * The outcome of a single check: did it pass, and why. Serialised into
 * verifications.checks.
 */
final readonly class CheckResult
{
    public function __construct(
        public CheckKey $key,
        public bool $passed,
        public string $detail,
    ) {}

    public static function pass(CheckKey $key, string $detail): self
    {
        return new self($key, true, $detail);
    }

    public static function fail(CheckKey $key, string $detail): self
    {
        return new self($key, false, $detail);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'label' => $this->key->label(),
            'article' => $this->key->article(),
            'passed' => $this->passed,
            'detail' => $this->detail,
        ];
    }
}
