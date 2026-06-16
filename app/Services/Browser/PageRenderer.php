<?php

declare(strict_types=1);

namespace App\Services\Browser;

use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Thin wrapper around Browsershot (headless Chromium) used to capture evidence
 * of live DGI/Harmony2 pages. Every method degrades gracefully — a null return
 * means the page could not be rendered (offline, timeout, portal down), so the
 * caller records "unverified" rather than failing the verification.
 *
 * On Windows the Puppeteer auto-resolver cannot locate Chrome when the user
 * path contains a space, so the binary is pinned via services.browsershot.
 */
final class PageRenderer
{
    public function __construct(
        private ?string $chromePath = null,
        private ?string $nodePath = null,
        private int $timeoutSeconds = 90,
        private int $delayMs = 3500,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            chromePath: config('services.browsershot.chrome_path'),
            nodePath: config('services.browsershot.node_path'),
            timeoutSeconds: (int) config('services.browsershot.timeout', 90),
        );
    }

    /** Full-page PNG screenshot of a URL after its JavaScript has run, or null. */
    public function screenshot(string $url): ?string
    {
        try {
            $png = $this->browser($url)->fullPage()->screenshot();

            return $png !== '' ? $png : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Rendered (post-JavaScript) body HTML of a URL, or null. */
    public function html(string $url): ?string
    {
        try {
            $html = $this->browser($url)->bodyHtml();

            return $html !== '' ? $html : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function browser(string $url): Browsershot
    {
        $browser = Browsershot::url($url)
            ->noSandbox()
            ->windowSize(1280, 1500)
            ->setDelay($this->delayMs)   // let the single-page app render its result
            ->timeout($this->timeoutSeconds);

        if (is_string($this->chromePath) && $this->chromePath !== '') {
            $browser->setChromePath($this->chromePath);
        }

        if (is_string($this->nodePath) && $this->nodePath !== '') {
            $browser->setNodeBinary($this->nodePath);
        }

        return $browser;
    }
}
