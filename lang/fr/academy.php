<?php

declare(strict_types=1);

return [
    // Page de connexion
    'login_title' => "Connexion à l'Academy",
    'login_subtitle' => 'Formation commerciale Elite Advisors',
    'email' => 'E-mail',
    'password' => 'Mot de passe',
    'remember' => 'Rester connecté',
    'sign_in' => 'Se connecter',
    'sign_out' => 'Déconnexion',
    'login_failed' => 'Ces identifiants ne correspondent à aucun compte Academy actif.',

    // Console d'administration — gestion des accès Academy
    'nav' => 'Accès Academy',
    'singular' => 'Membre Academy',
    'plural' => 'Accès Academy',
    'add' => 'Ajouter un collaborateur',
    'add_desc' => "Créez un accès pour un membre de l'équipe. Il accède uniquement au module de formation — jamais à la console d'administration ni à un espace de travail.",
    'f_name' => 'Nom complet',
    'f_email' => 'E-mail',
    'f_password' => 'Mot de passe',
    'f_password_help' => 'Au moins 8 caractères. Laissez vide pour en générer un automatiquement.',
    'col_active' => 'Actif',
    'col_last_login' => 'Dernière connexion',
    'never' => 'Jamais',
    'reset_password' => 'Réinitialiser le mot de passe',
    'reset_desc' => 'Définir un nouveau mot de passe pour ce membre.',
    'toggle_active' => 'Activer / désactiver',
    'activated' => 'Accès activé.',
    'deactivated' => 'Accès désactivé.',
    'delete' => "Retirer l'accès",
    'added_title' => 'Accès Academy créé',
    'email_taken' => 'Un compte Academy avec cet e-mail existe déjà.',
    'cred_url' => 'Connexion sur',
    'cred_email' => 'E-mail',
    'cred_password' => 'Mot de passe',
    'cred_once' => 'Communiquez-le au collaborateur — affiché une seule fois.',
    'cred_emailed' => 'Également envoyé par e-mail.',
    'cred_not_emailed' => "L'e-mail n'a pas pu être envoyé — communiquez le mot de passe manuellement.",
    'pw_reset_title' => 'Nouveau mot de passe défini',
    'col_pending' => 'Doit définir le mot de passe',

    // Changement de mot de passe à la première connexion
    'pw_change_title' => 'Définissez votre mot de passe',
    'pw_change_sub' => 'Choisissez un nouveau mot de passe pour finaliser votre accès.',
    'pw_new' => 'Nouveau mot de passe',
    'pw_confirm' => 'Confirmez le mot de passe',
    'pw_save' => 'Enregistrer et continuer',

    // E-mail d'intégration
    'mail_subject' => 'Votre accès Elite Advisors Academy',
    'mail_greeting' => 'Bonjour :name,',
    'mail_intro' => "Vous avez reçu un accès à l'Elite Advisors Academy — notre plateforme de formation commerciale. Connectez-vous avec les informations ci-dessous ; il vous sera demandé de définir votre propre mot de passe à la première connexion.",
    'mail_cta' => "Ouvrir l'Academy",
    'mail_note' => 'Pour votre sécurité, ce mot de passe à usage unique doit être changé lors de votre première connexion.',
];
