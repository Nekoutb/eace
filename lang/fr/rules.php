<?php

declare(strict_types=1);

return [
    'r1' => [
        'title' => 'NIU absent ou de format invalide',
        'message' => 'Le NIU est absent ou mal formé : la TVA portée sur la facture '
            .'n’est pas déductible et la facture est réputée incomplète.',
    ],

    'r2' => [
        'title' => 'NIU introuvable au fichier',
        'message' => 'Le NIU n’apparaît pas au fichier des contribuables. Vérifiez les '
            .'caractères ambigus ; à défaut, le NIU est probablement fictif — '
            .'refusez la transaction et signalez-le.',
    ],

    'r3' => [
        'title_revoked' => 'NIU radié',
        'message_revoked' => 'Le NIU est radié : toute facture émise après la radiation est nulle '
            .'fiscalement et la TVA non déductible.',
        'title_suspended' => 'NIU suspendu',
        'message_suspended' => 'Le NIU est suspendu : n’engagez aucun nouveau paiement tant que le '
            .'statut n’est pas redevenu « Actif ».',
    ],

    'r4' => [
        'title' => 'Hors fichier des contribuables actifs',
        'message' => 'Le contribuable est immatriculé mais absent du fichier des '
            .'contribuables actifs : la TVA d’amont n’ouvre pas droit à déduction.',
    ],

    'r5' => [
        'title' => 'Régime fiscal incompatible avec la TVA',
        'message' => 'La facture porte de la TVA alors que le fournisseur n’est pas au '
            .'régime du réel : cette TVA n’est pas déductible.',
    ],

    'r6' => [
        'title' => 'Type de NIU incohérent (P/M)',
        'message' => 'La première lettre du NIU ne correspond pas au type attendu : le NIU '
            .'appartient probablement au dirigeant à titre personnel, pas à l’entité '
            .'facturante. Exigez une clarification écrite.',
    ],

    'r7' => [
        'title' => 'Discordance de raison sociale',
        'message' => 'Le nom sur la facture diffère de la raison sociale enregistrée au '
            .'fichier pour ce NIU. Toute discordance impose une clarification écrite.',
    ],

    'r9' => [
        'title' => 'Paiement en espèces ≥ 100 000 F',
        'message' => 'Une opération d’au moins 100 000 F payée en espèces n’ouvre pas droit '
            .'à déduction de la TVA. Réglez par voie bancaire.',
    ],

    'r10' => [
        'title' => 'ACF en cours de validité requise',
        'message' => 'Cette opération (règlement public, export ou transfert à l’étranger) '
            .'exige une Attestation de Conformité Fiscale en cours de validité. '
            .'Son absence entraîne l’irrecevabilité.',
    ],

    'r11' => [
        'title' => 'Fournisseur étranger',
        'message' => 'Fournisseur étranger hors fichier camerounais : opérez la TSR de 15 % '
            .'sur le brut versé et auto-liquidez la TVA si le service est consommé au '
            .'Cameroun. Conservez le contrat et les justificatifs.',
    ],
];
