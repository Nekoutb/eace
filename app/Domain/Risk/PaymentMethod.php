<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * How a transaction is settled. Matters for CGI art. 143 (1)(d): an operation
 * of at least 100 000 FCFA paid in cash is not VAT-deductible.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('app.payment_method.'.$this->value);
    }
}
