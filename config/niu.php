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
];
