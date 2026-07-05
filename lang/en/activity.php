<?php

return [
    'nav' => 'Client activity',
    'title' => 'Client activity',
    'subheading' => 'How intensively each client uses the platform — reach out before they go quiet.',

    'chart_heading' => 'Daily activity — last 30 days',
    'chart_desc' => 'Invoices recorded, payments checked and ACF certificates processed per day. Use the filter to focus on one client.',
    'all_clients' => 'All clients',

    'ds_recorded' => 'Invoices recorded',
    'ds_settled' => 'Payments checked',
    'ds_acfs' => 'ACF certificates processed',

    'matrix_heading' => 'Adoption matrix — where each client stands',
    'matrix_desc' => 'Every step a client is expected to reach, detected from their workspace. A dash is where to engage: call, guide, or train.',
    'matrix_empty' => 'No clients yet — the matrix fills in as workspaces are created.',
    'matrix_step' => 'Expected step',
    'matrix_progress' => 'Progress',

    'step_signed_in' => 'Signed in',
    'hint_signed_in' => 'At least one user has signed into the workspace',
    'step_two_fa' => '2FA set up',
    'hint_two_fa' => 'Authenticator app enrolled (users enrolled / total)',
    'step_settings' => 'Company settings',
    'hint_settings' => 'Own entity name + NIU saved (drives the art. 150 invoice checks)',
    'step_team' => 'Team on board',
    'hint_team' => 'More than one user in the workspace (roles assigned)',
    'step_acf' => 'First ACF loaded',
    'hint_acf' => 'At least one tax-clearance certificate processed',
    'step_recording' => 'Compliance engine — invoices',
    'hint_recording' => 'At least one invoice recorded (recording stage)',
    'step_settlement' => 'Compliance engine — payments',
    'hint_settlement' => 'At least one payment checked (settlement stage)',

    'table_heading' => 'Clients — last 30 days',
    'table_desc' => 'Volumes over the trailing 30 days, and when each client was last active. Red means silent for over 14 days — worth a call.',
    'col_recorded' => 'Invoices (30 d)',
    'col_settled' => 'Payments (30 d)',
    'col_acfs' => 'ACFs (30 d)',
    'col_total' => 'Total (30 d)',
    'col_last' => 'Last activity',
    'never' => 'Never',
];
