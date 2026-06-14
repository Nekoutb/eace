<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * The four checks performed before accepting (or paying) a vendor invoice.
 */
enum CheckKey: string
{
    case DocumentAuthenticity = 'document_authenticity';
    case NiuStatus = 'niu_status';
    case NiuExistence = 'niu_existence';
    case AcfValidity = 'acf_validity';

    public function label(): string
    {
        return (string) __('app.check.'.$this->value);
    }

    public function article(): string
    {
        return match ($this) {
            self::DocumentAuthenticity => 'CGI art. L 94 bis',
            self::NiuStatus => 'CGI art. 143',
            self::NiuExistence => 'CGI art. L 100',
            self::AcfValidity => 'CGI art. L 94 bis',
        };
    }
}
