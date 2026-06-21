<?php

declare(strict_types=1);

namespace App\Services\Evidence;

/**
 * The descriptive evidence filename convention — one place so every artifact is
 * named the same way and the rules are testable. Each name ends with the
 * verification date+time (Y-m-d_His), which keeps names unique within a folder.
 */
final class EvidenceNaming
{
    /** The tax-clearance certificate itself: ACF_<Vendor>_<datetime>.pdf */
    public static function acfPdf(EvidenceContext $ctx): string
    {
        return 'ACF_'.self::who($ctx).'_'.$ctx->stamp().'.pdf';
    }

    /** The Harmony2 authentication of the ACF: ACF_Authentic_<620 ref>_<datetime>.png */
    public static function acfAuthentic(string $reference, EvidenceContext $ctx): string
    {
        return 'ACF_Authentic_'.EvidenceStore::sanitize($reference, 'ref').'_'.$ctx->stamp().'.png';
    }

    /** The live DGI NIU consultation: NIU_<Status>_<NIU>_<datetime>.png */
    public static function niuStatus(?string $status, ?string $niu, EvidenceContext $ctx): string
    {
        return 'NIU_'.EvidenceStore::sanitize($status, 'Status').'_'.EvidenceStore::sanitize($niu, 'NIU').'_'.$ctx->stamp().'.png';
    }

    /** The taxpayer-register existence proof: TaxRegister_<download date>_<Vendor>_<datetime>.png */
    public static function taxRegister(?string $downloadDate, EvidenceContext $ctx): string
    {
        return 'TaxRegister_'.EvidenceStore::sanitize($downloadDate, 'date').'_'.self::who($ctx).'_'.$ctx->stamp().'.png';
    }

    /** The scanned invoice: Invoice_<reference>_<datetime>.<ext> */
    public static function invoice(?string $reference, string $extension, EvidenceContext $ctx): string
    {
        $ext = EvidenceStore::sanitize($extension, 'pdf');

        return 'Invoice_'.EvidenceStore::sanitize($reference, 'ref').'_'.$ctx->stamp().'.'.$ext;
    }

    /** The vendor token used in names: the name when known, else the NIU. */
    private static function who(EvidenceContext $ctx): string
    {
        return EvidenceStore::sanitize($ctx->vendorName ?? $ctx->vendorNiu, 'vendor');
    }
}
