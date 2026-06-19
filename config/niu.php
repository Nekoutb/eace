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
     * Whether two-factor authentication (authenticator-app / TOTP) is MANDATORY
     * for every user — each must enrol before reaching the app. Kept as a switch
     * (not a constant) so it can be relaxed from the environment in an emergency
     * without a redeploy.
     */
    'two_factor_required' => (bool) env('NIU_TWO_FACTOR_REQUIRED', true),
];
