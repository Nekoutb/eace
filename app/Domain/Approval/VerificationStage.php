<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * When the four checks are run. The same checks run at both moments because a
 * vendor's tax standing can change over the payment term (CGI workflow).
 */
enum VerificationStage: string
{
    case Recording = 'recording';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Recording => 'Enregistrement facture',
            self::Settlement => 'Règlement facture',
        };
    }
}
