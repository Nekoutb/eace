<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * The outcome of a single check: did it pass, and why. Serialised into
 * verifications.checks. `passed` is null when the check could not be
 * performed at all (e.g. no tax clearance certificate exists to examine) —
 * distinct from a failure, but never sufficient for acceptance.
 */
final readonly class CheckResult
{
    public function __construct(
        public CheckKey $key,
        public ?bool $passed,
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

    public static function notPerformed(CheckKey $key, string $detail): self
    {
        return new self($key, null, $detail);
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
