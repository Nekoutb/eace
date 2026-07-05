<?php

return [
    'nav' => 'Services & monitoring',
    'singular' => 'Service',
    'plural' => 'Services',

    'add' => 'Add a service',
    'add_desc' => 'Record a subscription or infrastructure service to track its renewal and uptime.',
    'check_now' => 'Check now',
    'checked' => ':n service(s) checked.',

    'cat' => [
        'hosting' => 'Hosting',
        'domain' => 'Domain',
        'storage' => 'Storage',
        'email' => 'Email',
        'api' => 'API',
        'ssl' => 'SSL',
        'other' => 'Other',
    ],
    'cycle' => [
        'monthly' => 'monthly',
        'quarterly' => 'quarterly',
        'yearly' => 'yearly',
        'one_off' => 'one-off',
    ],

    'f_name' => 'Name',
    'f_provider' => 'Provider',
    'f_category' => 'Category',
    'f_powers' => 'Powers',
    'f_amount' => 'Amount',
    'f_currency' => 'Currency',
    'f_cycle' => 'Billing cycle',
    'f_renews_at' => 'Renews on',
    'f_auto_pay' => 'Auto-pay',
    'f_auto_pay_help' => 'The provider charges a card automatically — the strongest guard against a lapse.',
    'f_health_url' => 'Health URL (optional)',
    'f_active' => 'Active',
    'f_notes' => 'Notes',

    'col_renewal' => 'Renewal',
    'col_uptime' => 'Uptime',

    'no_date' => 'No date',
    'due_today' => 'Due today',
    'in_days' => 'in :n d',
    'overdue_days' => 'overdue :n d',
    'up_up' => 'Up',
    'up_down' => 'Down',
    'autopay_on' => 'auto-pay on',
    'autopay_off' => 'auto-pay OFF',
    'no_url' => 'No health URL is set for this service.',

    'empty_heading' => 'No services yet',
    'empty_desc' => 'Add your hosting, domain, storage, email and API subscriptions to track their renewals.',

    'edit' => 'Edit',
    'delete' => 'Delete',
    'ping' => 'Ping now',

    'digest_title' => 'Operations monitoring',
    'overdue' => 'Overdue',
    'due_soon' => 'Renewing soon',
    'sites_down' => 'Sites down',

    'mail_subject' => 'Operations alert — :n item(s) need attention',
    'mail_heading' => 'Services needing attention',
    'mail_intro' => 'The services below are overdue, renewing soon, or unreachable. Renew or pay them before they lapse.',
    'mail_cta' => 'Open the monitoring dashboard',
    'mail_note' => 'You receive this because you are listed as the operations contact. Turning on auto-pay removes the renewal risk entirely.',

    'stat_services' => 'Monitored services',
    'stat_services_desc' => 'Active subscriptions tracked',
    'stat_due' => 'Renewing soon',
    'stat_overdue' => 'Overdue',
    'stat_down' => 'Sites down',
];
