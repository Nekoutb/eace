<?php

return [
    'nav' => 'Services & surveillance',
    'singular' => 'Service',
    'plural' => 'Services',

    'add' => 'Ajouter un service',
    'add_desc' => 'Enregistrez un abonnement ou un service d’infrastructure pour suivre son renouvellement et sa disponibilité.',
    'check_now' => 'Vérifier maintenant',
    'checked' => ':n service(s) vérifié(s).',

    'cat' => [
        'hosting' => 'Hébergement',
        'domain' => 'Domaine',
        'storage' => 'Stockage',
        'email' => 'Messagerie',
        'api' => 'API',
        'ssl' => 'SSL',
        'other' => 'Autre',
    ],
    'cycle' => [
        'monthly' => 'mensuel',
        'quarterly' => 'trimestriel',
        'yearly' => 'annuel',
        'one_off' => 'ponctuel',
    ],

    'f_name' => 'Nom',
    'f_provider' => 'Fournisseur',
    'f_category' => 'Catégorie',
    'f_powers' => 'Utilisé pour',
    'f_amount' => 'Montant',
    'f_currency' => 'Devise',
    'f_cycle' => 'Périodicité',
    'f_renews_at' => 'Renouvellement le',
    'f_auto_pay' => 'Paiement auto.',
    'f_auto_pay_help' => 'Le fournisseur débite automatiquement une carte — la meilleure protection contre une interruption.',
    'f_health_url' => 'URL de santé (optionnel)',
    'f_active' => 'Actif',
    'f_notes' => 'Notes',

    'col_renewal' => 'Renouvellement',
    'col_uptime' => 'Disponibilité',

    'no_date' => 'Sans date',
    'due_today' => 'Échéance aujourd’hui',
    'in_days' => 'dans :n j',
    'overdue_days' => 'en retard de :n j',
    'up_up' => 'En ligne',
    'up_down' => 'Hors ligne',
    'autopay_on' => 'paiement auto. activé',
    'autopay_off' => 'paiement auto. INACTIF',
    'no_url' => 'Aucune URL de santé définie pour ce service.',

    'empty_heading' => 'Aucun service pour l’instant',
    'empty_desc' => 'Ajoutez vos abonnements d’hébergement, domaine, stockage, messagerie et API pour suivre leurs renouvellements.',

    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'ping' => 'Tester maintenant',

    'digest_title' => 'Surveillance des opérations',
    'overdue' => 'En retard',
    'due_soon' => 'Renouvellement proche',
    'sites_down' => 'Sites hors ligne',

    'mail_subject' => 'Alerte opérations — :n élément(s) à traiter',
    'mail_heading' => 'Services à traiter',
    'mail_intro' => 'Les services ci-dessous sont en retard, bientôt à renouveler, ou injoignables. Renouvelez ou réglez-les avant toute interruption.',
    'mail_cta' => 'Ouvrir le tableau de bord de surveillance',
    'mail_note' => 'Vous recevez ce message car vous êtes le contact des opérations. Activer le paiement automatique supprime totalement le risque d’interruption.',

    'stat_services' => 'Services surveillés',
    'stat_services_desc' => 'Abonnements actifs suivis',
    'stat_due' => 'Renouvellement proche',
    'stat_overdue' => 'En retard',
    'stat_down' => 'Sites hors ligne',
];
