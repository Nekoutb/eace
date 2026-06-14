<?php

declare(strict_types=1);

return [
    'r1' => [
        'title' => 'NIU missing or in an invalid format',
        'message' => 'The NIU is missing or malformed: the VAT shown on the invoice '
            .'is not deductible and the invoice is deemed incomplete.',
    ],

    'r2' => [
        'title' => 'NIU not found in the taxpayer file',
        'message' => 'The NIU does not appear in the taxpayer file. Check for '
            .'ambiguous characters; failing that, the NIU is probably fictitious — '
            .'reject the transaction and report it.',
    ],

    'r3' => [
        'title_revoked' => 'NIU struck off',
        'message_revoked' => 'The NIU has been struck off: any invoice issued after the removal is '
            .'fiscally void and the VAT is not deductible.',
        'title_suspended' => 'NIU suspended',
        'message_suspended' => 'The NIU is suspended: do not commit to any new payment until the '
            .'status has returned to “Active”.',
    ],

    'r4' => [
        'title' => 'Not on the active taxpayer file',
        'message' => 'The taxpayer is registered but absent from the active '
            .'taxpayer file: the input VAT does not give rise to a right of deduction.',
    ],

    'r5' => [
        'title' => 'Tax regime incompatible with VAT',
        'message' => 'The invoice charges VAT even though the supplier is not under '
            .'the actual-earnings regime (régime du réel): this VAT is not deductible.',
    ],

    'r6' => [
        'title' => 'Inconsistent NIU type (P/M)',
        'message' => 'The first letter of the NIU does not match the expected type: the NIU '
            .'probably belongs to the manager in a personal capacity, not to the invoicing '
            .'entity. Require written clarification.',
    ],

    'r7' => [
        'title' => 'Business-name mismatch',
        'message' => 'The name on the invoice differs from the business name registered in the '
            .'file for this NIU. Any discrepancy requires written clarification.',
    ],

    'r9' => [
        'title' => 'Cash payment ≥ 100,000 F',
        'message' => 'An operation of at least 100,000 F paid in cash does not give rise to a right '
            .'to deduct VAT. Settle through a banking channel.',
    ],

    'r10' => [
        'title' => 'Valid ACF required',
        'message' => 'This operation (public settlement, export or transfer abroad) '
            .'requires a valid Tax Compliance Certificate (ACF). '
            .'Its absence renders the request inadmissible.',
    ],

    'r11' => [
        'title' => 'Foreign supplier',
        'message' => 'Foreign supplier outside the Cameroonian file: apply the 15% TSR '
            .'withholding on the gross amount paid and self-assess the VAT if the service is '
            .'consumed in Cameroon. Keep the contract and supporting documents.',
    ],
];
