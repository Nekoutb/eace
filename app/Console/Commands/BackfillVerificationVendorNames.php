<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Niu\NiuNumber;
use App\Models\Tenant;
use App\Models\Verification;
use App\Services\Registry\TaxpayerFileResolver;
use Illuminate\Console\Command;

/**
 * One-off: fill verifications.vendor_name on rows created before the column
 * existed — from the central taxpayer register by NIU, else the linked ACF's
 * taxpayer name, else the invoice's OCR'd supplier name. Idempotent: only
 * touches rows where vendor_name is still null.
 */
class BackfillVerificationVendorNames extends Command
{
    protected $signature = 'verifications:backfill-vendor-names';

    protected $description = 'Fill vendor_name on existing verifications from the register, ACF, or invoice data.';

    public function handle(TaxpayerFileResolver $resolver): int
    {
        $filled = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use (&$filled, $resolver): void {
            $tenant->run(function () use (&$filled, $resolver): void {
                Verification::query()
                    ->whereNull('vendor_name')
                    ->with('acfCertificate')
                    ->cursor()
                    ->each(function (Verification $verification) use (&$filled, $resolver): void {
                        $name = $this->resolveName($verification, $resolver);

                        if ($name === null) {
                            return;
                        }

                        $verification->forceFill(['vendor_name' => $name])->saveQuietly();
                        $filled++;
                    });
            });
        });

        $this->info("Filled vendor_name on {$filled} verification(s).");

        return self::SUCCESS;
    }

    private function resolveName(Verification $verification, TaxpayerFileResolver $resolver): ?string
    {
        $niu = filled($verification->niu) ? NiuNumber::tryFrom((string) $verification->niu) : null;

        if ($niu !== null) {
            $record = $resolver->resolve($niu);

            if ($record?->taxpayerName !== null) {
                return $record->taxpayerName;
            }
        }

        return $verification->acfCertificate->taxpayer_name
            ?? ($verification->invoice_data['supplier_name'] ?? null);
    }
}
