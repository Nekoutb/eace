<?php

return [
    /*
     * Days before an ACF's expiry at which it is treated as "expiring soon" and
     * may no longer be reused at settlement (CGI art. L 94 bis 3-month validity).
     * A setting, not a constant — tenants may tighten it later.
     */
    'acf_validity_buffer_days' => (int) env('NIU_ACF_BUFFER_DAYS', 5),
];
