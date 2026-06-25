<?php

declare(strict_types=1);

return [
    // Sign-in page
    'login_title' => 'Sign in to the Academy',
    'login_subtitle' => 'Elite Advisors sales training',
    'email' => 'Email',
    'password' => 'Password',
    'remember' => 'Keep me signed in',
    'sign_in' => 'Sign in',
    'sign_out' => 'Sign out',
    'login_failed' => "Those credentials don't match an active Academy account.",

    // Admin console — Academy access management
    'nav' => 'Academy access',
    'singular' => 'Academy member',
    'plural' => 'Academy access',
    'add' => 'Add a staff member',
    'add_desc' => 'Create a sign-in for a team member. They reach the training module only — never the admin console or a workspace.',
    'f_name' => 'Full name',
    'f_email' => 'Email',
    'f_password' => 'Password',
    'f_password_help' => 'At least 8 characters. Leave blank to generate one automatically.',
    'col_active' => 'Active',
    'col_last_login' => 'Last sign-in',
    'never' => 'Never',
    'reset_password' => 'Reset password',
    'reset_desc' => 'Set a new password for this member.',
    'toggle_active' => 'Enable / disable',
    'activated' => 'Access enabled.',
    'deactivated' => 'Access disabled.',
    'delete' => 'Remove access',
    'added_title' => 'Academy access created',
    'email_taken' => 'An Academy account with this email already exists.',
    'cred_url' => 'Sign in at',
    'cred_email' => 'Email',
    'cred_password' => 'Password',
    'cred_once' => 'Share this with the staff member — it is shown once.',
    'cred_emailed' => 'Also emailed to them.',
    'cred_not_emailed' => "Email couldn't be sent — share the password manually.",
    'pw_reset_title' => 'New password set',
    'col_pending' => 'Must set password',

    // First-login password change
    'pw_change_title' => 'Set your password',
    'pw_change_sub' => 'Choose a new password to finish setting up your access.',
    'pw_new' => 'New password',
    'pw_confirm' => 'Confirm password',
    'pw_save' => 'Save and continue',

    // Onboarding email
    'mail_subject' => 'Your Elite Advisors Academy access',
    'mail_greeting' => 'Hello :name,',
    'mail_intro' => "You've been given access to the Elite Advisors Academy — our sales-training platform. Sign in with the details below; you'll be asked to set your own password on first sign-in.",
    'mail_cta' => 'Open the Academy',
    'mail_note' => 'For your security, this one-time password must be changed the first time you sign in.',
];
