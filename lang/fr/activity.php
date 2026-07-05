<?php

return [
    'nav' => 'Activité clients',
    'title' => 'Activité clients',
    'subheading' => 'L’intensité d’utilisation de la plateforme par chaque client — contactez-les avant qu’ils ne décrochent.',

    'chart_heading' => 'Activité quotidienne — 30 derniers jours',
    'chart_desc' => 'Factures enregistrées, paiements contrôlés et attestations (ACF) traitées par jour. Utilisez le filtre pour cibler un client.',
    'all_clients' => 'Tous les clients',

    'ds_recorded' => 'Factures enregistrées',
    'ds_settled' => 'Paiements contrôlés',
    'ds_acfs' => 'Attestations (ACF) traitées',

    'matrix_heading' => 'Matrice d’adoption — où en est chaque client',
    'matrix_desc' => 'Chaque étape attendue d’un client, détectée depuis son espace. Un tiret = le moment d’intervenir : appeler, guider, former.',
    'matrix_empty' => 'Aucun client pour l’instant — la matrice se remplit à mesure que les espaces sont créés.',
    'matrix_step' => 'Étape attendue',
    'matrix_progress' => 'Progression',

    'step_signed_in' => 'Connexion effectuée',
    'hint_signed_in' => 'Au moins un utilisateur s’est connecté à l’espace',
    'step_two_fa' => '2FA configurée',
    'hint_two_fa' => 'Application d’authentification enrôlée (enrôlés / total)',
    'step_settings' => 'Paramètres de l’entreprise',
    'hint_settings' => 'Nom + NIU de l’entité enregistrés (alimente les contrôles art. 150)',
    'step_team' => 'Équipe en place',
    'hint_team' => 'Plus d’un utilisateur dans l’espace (rôles attribués)',
    'step_acf' => 'Première ACF chargée',
    'hint_acf' => 'Au moins une attestation de conformité fiscale traitée',
    'step_recording' => 'Moteur de conformité — factures',
    'hint_recording' => 'Au moins une facture enregistrée (phase enregistrement)',
    'step_settlement' => 'Moteur de conformité — paiements',
    'hint_settlement' => 'Au moins un paiement contrôlé (phase règlement)',

    'table_heading' => 'Clients — 30 derniers jours',
    'table_desc' => 'Volumes sur les 30 derniers jours et dernière activité de chaque client. Rouge : silencieux depuis plus de 14 jours — un appel s’impose.',
    'col_recorded' => 'Factures (30 j)',
    'col_settled' => 'Paiements (30 j)',
    'col_acfs' => 'ACF (30 j)',
    'col_total' => 'Total (30 j)',
    'col_last' => 'Dernière activité',
    'never' => 'Jamais',
];
