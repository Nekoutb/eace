<?php

declare(strict_types=1);

namespace App\Domain\ThirdParty;

/**
 * A counterparty's role from the tenant's point of view. A NIU must be verified
 * before paying a supplier (deductibility) and is required on invoices issued
 * to clients (CGI art. 150 — NIU of supplier AND client).
 */
enum ThirdPartyType: string
{
    case Supplier = 'supplier';
    case Client = 'client';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Fournisseur',
            self::Client => 'Client',
            self::Both => 'Fournisseur et client',
        };
    }
}
