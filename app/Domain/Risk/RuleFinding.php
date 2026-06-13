<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * The result of a rule that fired: which rule, the CGI article it rests on, a
 * French explanation, a severity, and the estimated FCFA exposure it carries.
 * Serialised into verifications.triggered_rules.
 */
final readonly class RuleFinding
{
    public function __construct(
        public string $code,        // e.g. "R1"
        public string $title,       // short label
        public string $article,     // e.g. "CGI art. L 101"
        public string $message,     // French explanation
        public RuleSeverity $severity,
        public int $exposureXaf = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'article' => $this->article,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'exposure_xaf' => $this->exposureXaf,
        ];
    }
}
