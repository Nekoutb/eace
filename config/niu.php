<?php

return [
    /*
     * Days before an ACF's expiry at which it is treated as "expiring soon" and
     * may no longer be reused at settlement (CGI art. L 94 bis 3-month validity).
     * A setting, not a constant — tenants may tighten it later.
     */
    'acf_validity_buffer_days' => (int) env('NIU_ACF_BUFFER_DAYS', 5),

    /*
     * Audit-log retention. After this many months a verification's bulky evidence
     * (the scanned PDFs) is purged to free space, keeping only the tamper-proof
     * manifest. The scheduled job only runs when this is set — null disables the
     * automatic purge, so evidence is never deleted until you opt in (export it
     * first via the Audit Log). Manual export + archive always work in the UI.
     */
    'retention_months' => env('NIU_RETENTION_MONTHS') !== null ? (int) env('NIU_RETENTION_MONTHS') : null,

    /*
     * The base domain new client subdomains are created under. A client with
     * subdomain "acme" is reached at acme.<base>. "localhost" in dev (works
     * natively, no hosts edit); set to your production apex (e.g. niushield.cm).
     */
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),

    /*
     * Per-workspace evidence storage quota, in megabytes. The administrator sets
     * it when provisioning a client; this is the default the form starts at.
     * Self-service trial signups get the smaller trial quota.
     */
    'default_allocated_space_mb' => (int) env('NIU_DEFAULT_SPACE_MB', 1024),
    'trial_allocated_space_mb' => (int) env('NIU_TRIAL_SPACE_MB', 250),

    /*
     * Whether prospective customers may self-provision a trial workspace from the
     * public sign-up page. Off in production unless you want open trials.
     */
    'self_service_signup' => (bool) env('NIU_SELF_SERVICE_SIGNUP', true),

    /*
     * Whether this is a multi-CLIENT SaaS — several isolated tenant workspaces,
     * each provisioned with its own subdomain (client.<base>) — or a SINGLE
     * workspace on the bare domain. Single-workspace (the default) hides the
     * central "Provision a client" admin feature and its subdomain: there is just
     * the one workspace. Flip to true (NIU_MULTI_CLIENT=true) to re-enable
     * provisioning additional client workspaces.
     */
    'multi_client' => (bool) env('NIU_MULTI_CLIENT', false),

    /*
     * Whether two-factor authentication (authenticator-app / TOTP) is MANDATORY
     * for every user — each must enrol before reaching the app. Kept as a switch
     * (not a constant) so it can be relaxed from the environment in an emergency
     * without a redeploy.
     */
    'two_factor_required' => (bool) env('NIU_TWO_FACTOR_REQUIRED', true),

    /*
     * The shared DGI taxpayer register (FICHIER … .xlsx). Downloaded for everyone
     * twice a week (Mon + Thu) into the central database and used by every
     * workspace for NIU controls.
     */
    'taxpayer_file' => [
        /*
         * Safety floor for a new snapshot, as a fraction of the last good one. A
         * download whose row count collapses below this (default: half) is
         * rejected and the live register is left untouched — guards against a
         * truncated or corrupt file silently wiping the register.
         */
        'min_row_ratio' => (float) env('NIU_TAXPAYER_MIN_ROW_RATIO', 0.5),
    ],

    /*
     * Immutable document storage on Wasabi (S3-compatible). When disabled (dev,
     * tests, or before credentials are set) EvidenceStore transparently falls
     * back to the local disk, so the app works either way. Keys follow
     * t/<tenant>/<doc_type>/<YYYY>/<MM>/v_<id>/<file>, confined to the active
     * tenant's prefix.
     */
    'wasabi' => [
        'enabled' => (bool) env('WASABI_ENABLED', false),
        'disk' => 'wasabi',
        // Server-side encryption applied to every object (AES-256 SSE-S3).
        'sse' => env('WASABI_SSE', 'AES256'),
        // Object Lock: evidence + manifests are sealed in COMPLIANCE mode (true
        // WORM — not even root can delete during the window); invoices + ACFs use
        // GOVERNANCE so a mis-upload can still be corrected by a privileged admin.
        'object_lock' => (bool) env('WASABI_OBJECT_LOCK', true),
        'retention_years' => (int) env('WASABI_RETENTION_YEARS', 2),
        // Document types sealed in COMPLIANCE mode (the rest use GOVERNANCE).
        'compliance_types' => ['evidence', 'manifests'],
    ],

    /*
     * Trusted timestamping of evidence captures (the DGI / Harmony2 screenshots).
     * Every capture carries a VISIBLE date/time band; when this is enabled each
     * capture's SHA-256 is also sealed by an independent RFC-3161 Timestamp
     * Authority, so the moment of capture is provable by a third party rather than
     * only asserted by our server clock. Disabled by default (dev/test never reach
     * the network); turn on in production with a TSA URL.
     */
    'timestamp' => [
        'enabled' => (bool) env('NIU_TIMESTAMP_ENABLED', false),
        // A free, public RFC-3161 authority by default; swap for a paid TSA (with
        // an SLA) by setting NIU_TSA_URL.
        'tsa_url' => env('NIU_TSA_URL', 'https://freetsa.org/tsr'),
        // Optional PEM bundle (TSA cert + its CA chain) used to VERIFY a token
        // offline — only needed by the verify tooling, not to obtain a token.
        'tsa_ca' => env('NIU_TSA_CA'),
        'hash_algo' => env('NIU_TSA_HASH', 'sha256'),
        'openssl_bin' => env('NIU_OPENSSL_BIN', 'openssl'),
        'http_timeout' => (int) env('NIU_TSA_TIMEOUT', 12),
        // Timezone for the human-readable capture band stamped onto each image.
        'timezone' => env('NIU_CAPTURE_TZ', 'Africa/Douala'),
        // How many times the retry job re-attempts a failed/pending seal.
        'max_attempts' => (int) env('NIU_TSA_MAX_ATTEMPTS', 5),
    ],
];
