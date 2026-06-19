<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * The installed pragmarx/google2fa-qrcode build already returns a complete
 * `data:image/svg+xml;base64,…` data URI from getQRCodeInline(). Filament's base
 * provider, when bacon-qr-code is present but the imagick extension is not, wraps
 * that output in ANOTHER `data:image/svg+xml;base64,` — double-encoding it, which
 * renders as a broken image in the 2FA set-up dialog. This override returns the
 * data URI verbatim so the QR code displays correctly (no imagick needed).
 */
class AppAuthentication extends BaseAppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        /** @var HasAppAuthentication $user */
        $user = Filament::auth()->user();

        return $this->google2FA->getQRCodeInline($this->getBrandName(), $this->getHolderName($user), $secret);
    }
}
