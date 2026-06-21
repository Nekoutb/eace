<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Support knowledge base
|--------------------------------------------------------------------------
|
| The ground truth the AI support assistant answers from. Each FAQ entry is a
| category + a representative question + the answer the bot should give (it adapts
| wording and replies in the customer's language). The bot answers from this by
| default and only hands a conversation to a human for the `escalate` topics below.
|
| This is intentionally plain data so it can be edited without touching code and
| surfaced as a help table. Keep answers accurate — never claim a feature, price,
| or guarantee that isn't true. After editing on the server, run `config:cache`.
|
*/

return [

    // Topics the bot answers itself (resolved = true, nothing sent to Telegram).
    'faq' => [

        // --- Greetings & small talk ------------------------------------------------
        ['category' => 'Greetings', 'q' => 'Hi / Hello / Bonjour / Good morning', 'a' => "Hello! I'm the Elite Advisors assistant. I hope you're doing well — how can I help you today? You can ask me about verifying a supplier's NIU, recording invoices, authenticating an ACF, your stored evidence, or your account."],
        ['category' => 'Greetings', 'q' => 'How are you?', 'a' => "I'm doing well, thank you for asking! How can I help you with your compliance work today?"],
        ['category' => 'Greetings', 'q' => 'Thank you / Thanks', 'a' => "You're very welcome — I'm glad I could help. Is there anything else you'd like to do?"],

        // --- About the platform ----------------------------------------------------
        ['category' => 'About', 'q' => 'What is Elite Advisors Compliance Engine?', 'a' => "It's a platform that helps Cameroon businesses confirm the fiscal compliance of their suppliers — verifying a supplier's NIU against the national taxpayer register, authenticating ACF tax-clearance certificates, and keeping tamper-proof evidence — before you record invoices or settle payments."],
        ['category' => 'About', 'q' => 'What can the platform do for me?', 'a' => 'You can verify supplier NIUs, authenticate ACFs, record invoices with automatic compliance checks, settle payments with a fresh check, store audit-grade evidence, generate compliance certificates, and track everything in an audit journal.'],

        // --- Getting started -------------------------------------------------------
        ['category' => 'Getting started', 'q' => 'How do I create an account or workspace?', 'a' => 'On the home page, click "Create your workspace", enter your organisation name, your name and work email, then confirm the verification email we send you. We create your private workspace and email you a one-time password to sign in.'],
        ['category' => 'Getting started', 'q' => 'What do I do after my first sign-in?', 'a' => "You'll set up two-factor authentication and choose your own password, and then you're ready. The dashboard's \"Getting started\" guide points you to recording your first invoice or verifying a supplier."],

        // --- Signing in & security -------------------------------------------------
        ['category' => 'Signing in', 'q' => 'How do I sign in?', 'a' => 'Go to the sign-in page, enter your email and password, then your two-factor code. First-time users are guided to set up 2FA.'],
        ['category' => 'Signing in', 'q' => 'I forgot my password / how do I reset it?', 'a' => "On the sign-in page click \"Forgot password?\", enter your email, and we'll send you a secure reset link. Two-factor authentication is still required after you reset."],
        ['category' => 'Security', 'q' => 'Why is two-factor authentication required?', 'a' => "Two-factor authentication adds a second check beyond your password to protect your compliance data, and it's mandatory for every account. Scan the QR code shown at first login with an authenticator app such as Google Authenticator."],
        ['category' => 'Security', 'q' => 'Is the platform secure?', 'a' => "Yes — it's served over encrypted HTTPS, every account uses two-factor authentication, and each workspace's data is strictly isolated from every other workspace."],

        // --- NIU verification ------------------------------------------------------
        ['category' => 'NIU verification', 'q' => 'What is a NIU?', 'a' => "A NIU (Numéro d'Identifiant Unique) is the unique taxpayer number issued by Cameroon's tax authority, the DGI. The platform checks a supplier's NIU against the official taxpayer register."],
        ['category' => 'NIU verification', 'q' => "How do I verify a supplier's NIU?", 'a' => "Open the Compliance Engine, enter or upload the supplier's details, and the platform matches the NIU against the national taxpayer register and tells you whether it's active and the name matches."],
        ['category' => 'NIU verification', 'q' => 'How up to date is the taxpayer register?', 'a' => 'The register is refreshed regularly from the official source, so your verifications reflect recent data.'],

        // --- ACF authentication ----------------------------------------------------
        ['category' => 'ACF', 'q' => 'What is an ACF?', 'a' => "An ACF (Attestation de Conformité Fiscale) is the tax-compliance certificate issued by the DGI. The platform authenticates it to confirm it's genuine and currently valid."],
        ['category' => 'ACF', 'q' => 'How do I authenticate an ACF?', 'a' => "When recording an invoice, upload the supplier's ACF. The platform reads it, verifies its authenticity and validity, and captures the result as evidence."],

        // --- Recording invoices ----------------------------------------------------
        ['category' => 'Recording invoices', 'q' => 'How do I record an invoice?', 'a' => "Open the Compliance Engine, start a new recording, and upload the supplier's ACF and the scanned invoice(s). The platform reads them, runs the compliance checks, and saves the outcome with full evidence."],
        ['category' => 'Recording invoices', 'q' => 'What checks are run on an invoice?', 'a' => "The platform verifies the supplier's NIU and the ACF's authenticity and validity, and runs conformity checks aligned with the General Tax Code (CGI, art. 150), then records a clear pass/fail outcome with evidence."],
        ['category' => 'Recording invoices', 'q' => 'Can one ACF cover several invoices?', 'a' => 'Yes — you can attach multiple invoices to a single ACF and record them together under the same authentication.'],

        // --- Settling payments -----------------------------------------------------
        ['category' => 'Settlement', 'q' => 'How do I settle a payment?', 'a' => "Use the settlement flow to re-verify the supplier's NIU at the time of payment (you can do this in batches) and record the settlement with its date and evidence."],
        ['category' => 'Settlement', 'q' => "What's the difference between recording and settling?", 'a' => 'Recording captures the invoice and its compliance evidence; settling confirms the supplier is still compliant when you actually pay, and logs the payment.'],

        // --- Data, storage & evidence (the "how is my data stored" answers) --------
        ['category' => 'Data & evidence', 'q' => 'How is my data stored? Is it safe?', 'a' => "Your evidence is kept in a secure, encrypted vault with write-once (tamper-proof) protection and trusted timestamps, isolated to your workspace. Once captured, evidence can't be altered or deleted, so it stands up to audit."],
        ['category' => 'Data & evidence', 'q' => 'Where do I find the evidence for a check?', 'a' => 'Open the verification or invoice record — each check stores its screenshots, documents and timestamps, which you can view or download from the evidence section.'],
        ['category' => 'Data & evidence', 'q' => 'Can evidence be changed or deleted?', 'a' => "No — evidence is stored write-once and timestamped, so it can't be tampered with after capture. That's exactly what makes it audit-grade."],
        ['category' => 'Data & evidence', 'q' => 'How long is my evidence kept?', 'a' => 'Evidence is retained for the long term so you always have a complete audit trail, with recent records kept readily accessible.'],

        // --- Certificates, audit, dashboard, settings, users -----------------------
        ['category' => 'Certificates', 'q' => 'How do I get a compliance certificate?', 'a' => 'Once a verification passes, you can generate and download a compliance certificate from the record or from the Compliance Certificates section.'],
        ['category' => 'Audit journal', 'q' => 'What does the audit journal record?', 'a' => 'It logs every action — who did what and when, with the taxpayer and invoice reference — giving you a complete, searchable trail.'],
        ['category' => 'Dashboard', 'q' => 'What does the dashboard show?', 'a' => 'It summarises your activity and compliance KPIs — recent verifications, evidence storage, and quick links to get things done.'],
        ['category' => 'Settings', 'q' => 'How do I set my business name or accepted name variations?', 'a' => 'In Settings you can set your registered business name and any accepted variations, so name-matching during a verification recognises them.'],
        ['category' => 'Users & roles', 'q' => 'How do I add a user, and what are the roles?', 'a' => 'An Administrator can add users in the Users section. There are three roles: Administrator (full access), Invoice recording (records invoices), and Invoice settler (settles payments).'],
        ['category' => 'Surveys', 'q' => 'What is the satisfaction survey?', 'a' => 'From time to time we send a short, anonymous survey about service quality and ease of use; your answers are anonymous and help us improve.'],

        // --- Troubleshooting -------------------------------------------------------
        ['category' => 'Troubleshooting', 'q' => "A page won't load or is slow", 'a' => "Please refresh the page first. If it keeps happening, tell me what you were doing and I'll have the team look into it."],
        ['category' => 'Troubleshooting', 'q' => 'My upload failed', 'a' => "Make sure the file is a clear PDF or image within the size limit and try again. If it still fails, let me know and we'll help."],
        ['category' => 'Troubleshooting', 'q' => "I can't sign in", 'a' => "Use \"Forgot password?\" to reset your password, and make sure you're entering your current two-factor code. If you're locked out, a specialist can help you regain access."],
    ],

    // Topics that ALWAYS need a person: the bot gives a short, polite holding reply
    // (a specialist will follow up) and the conversation is escalated to the team.
    'escalate' => [
        'Pricing, plans, billing, the invoices we send you, refunds, or contracts',
        "Account changes you can't make yourself — changing your email, closing the account, deleting data, or adding/removing users you don't have rights for",
        'A request for fiscal, tax, legal or accounting advice, or a judgment on whether a specific NIU, supplier or invoice is compliant (you cannot see their records or give such advice)',
        'Any complaint or clear dissatisfaction',
        'A reproducible bug, an error message, or an outage',
        'An explicit request to speak with a person',
    ],
];
