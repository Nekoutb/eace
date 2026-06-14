<?php

return [
    'nav' => [
        'verifications' => 'Vérifications',
        'compliance_engine' => 'Moteur de conformité',
        'certificates' => 'Attestations (ACF)',
    ],

    'verdict' => [
        'conforme' => 'Conforme',
        'conforme_avec_reserves' => 'Conforme avec réserves',
        'suspendu' => 'Suspendu',
        'radie' => 'Radié',
        'introuvable' => 'Introuvable',
        'format_invalide' => 'Format invalide',
    ],

    'approval' => [
        'accepted' => 'Facture acceptée',
        'rejected' => 'Facture refusée',
    ],

    'stage' => [
        'recording' => 'Enregistrement facture',
        'settlement' => 'Règlement facture',
    ],

    'acf_status' => [
        'valid' => 'Valide',
        'expiring_soon' => 'Bientôt expirée',
        'expired' => 'Expirée',
        'not_authentic' => 'Non authentique',
    ],

    'acf_auth' => [
        'authentic' => 'Authentique',
        'not_authentic' => 'Non authentique',
        'unverified' => 'Non vérifiée',
    ],

    'entity_type' => [
        'physique' => 'Personne physique',
        'morale' => 'Personne morale',
    ],

    'third_party_type' => [
        'supplier' => 'Fournisseur',
        'client' => 'Client',
        'both' => 'Fournisseur et client',
    ],

    'document_type' => [
        'acf' => 'Attestation de Conformité Fiscale',
        'rccm' => 'RCCM',
        'cnps' => 'Attestation CNPS',
        'rib' => 'RIB',
        'other' => 'Autre',
    ],

    'payment_method' => [
        'cash' => 'Espèces',
        'transfer' => 'Virement',
        'mobile_money' => 'Mobile Money',
        'cheque' => 'Chèque',
        'other' => 'Autre',
    ],

    'fiscalis_status' => [
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'radie' => 'Radié',
    ],

    'severity' => [
        'info' => 'Information',
        'warning' => 'Avertissement',
        'blocking' => 'Bloquant',
    ],

    'check' => [
        'document_authenticity' => 'Authenticité du document',
        'niu_status' => 'Statut du NIU',
        'niu_existence' => 'Existence au fichier',
        'acf_validity' => 'Validité de l’ACF',
        'msg' => [
            'auth_authentic' => 'Document confirmé authentique auprès du système Harmony2.',
            'auth_not_authentic' => 'Document non authentique : absent de la liste publiée par la DGI.',
            'auth_unverified' => 'Authenticité non vérifiée : aucune ACF authentifiée n’est rattachée.',
            'status_actif_on_file' => 'NIU actif et inscrit au fichier des contribuables actifs.',
            'status_actif_off_file' => 'NIU immatriculé mais absent du fichier des contribuables actifs : TVA non déductible (art. 143 (1)(b)).',
            'status_suspendu' => 'NIU suspendu : aucun nouveau paiement tant qu’il n’est pas réactivé.',
            'status_radie' => 'NIU radié : toute facture émise après la radiation est nulle fiscalement.',
            'status_unknown' => 'Statut du NIU indéterminé (NIU introuvable au fichier).',
            'existence_invalid' => 'NIU absent ou de format invalide — impossible à rechercher.',
            'existence_present' => 'NIU présent au fichier national des contribuables.',
            'existence_absent' => 'NIU introuvable au fichier : probablement fictif. Refusez la transaction.',
            'validity_valid' => 'ACF en cours de validité.',
            'validity_expiring_recording' => 'ACF en cours de validité (proche de l’échéance — à renouveler avant le règlement).',
            'validity_expiring_settlement' => 'ACF bientôt expirée : paiement impossible au règlement, exigez une attestation à jour.',
            'validity_expired' => 'ACF expirée : la clearance fiscale n’est plus valable.',
            'validity_not_authentic' => 'ACF non authentique : validité sans valeur.',
            'validity_none' => 'Aucune ACF rattachée à cette vérification.',
        ],
    ],

    'ui' => [
        'lang' => 'Langue',
        'identifier' => 'Identifiant',
        'record_invoice' => 'Enregistrer une facture',
        'settle_batch' => 'Vérifier un lot (règlement)',
        'verify_niu' => 'Vérifier un NIU',
        'import_acf' => 'Importer une ACF',
        'verify' => 'Vérifier',
        'import' => 'Importer',
        'verify_batch' => 'Vérifier le lot',
        // fields
        'niu' => 'NIU',
        'niu_to_verify' => 'NIU à vérifier',
        'niu_if_no_acf' => 'NIU (si pas d’ACF)',
        'niu_batch' => 'NIU à régler',
        'acf_reference' => 'Référence ACF',
        'acf_reference_optional' => 'Référence ACF (optionnel)',
        'acf_pdf' => 'Attestation de Conformité Fiscale (PDF)',
        'reference' => 'Référence ACF',
        'taxpayer' => 'Contribuable',
        'taxpayer_name' => 'Contribuable',
        'regime' => 'Régime',
        'centre' => 'Centre des impôts',
        'status' => 'Statut',
        'validity' => 'Validité',
        'authenticity_harmony' => 'Authenticité (Harmony2)',
        'issued_at' => 'Signée le',
        'expires_at' => 'Expire le',
        'validity_duration' => 'Durée de validité',
        'months' => 'mois',
        'auth_url' => 'Lien d’authentification',
        'stage' => 'Étape',
        'approval' => 'Approbation',
        'approval_invoice' => 'Approbation de la facture',
        'verdict' => 'Verdict',
        'exposure' => 'Exposition',
        'exposure_estimated' => 'Exposition estimée',
        'third_party' => 'Tiers',
        'invoice_amount' => 'Montant de la facture (FCFA)',
        'invoice_name' => 'Nom / raison sociale sur la facture',
        'payment_method' => 'Mode de paiement',
        'vat_charged' => 'TVA facturée',
        'verified_at' => 'Vérifié le',
        'raw_input' => 'Saisie d’origine',
        'snapshot' => 'Preuve (snapshot central)',
        'created_at' => 'Créée le',
        // sections
        'section_decision' => 'Décision',
        'section_four_checks' => 'Les quatre contrôles',
        'section_verdict_exposure' => 'Verdict & exposition',
        'section_triggered_rules' => 'Règles déclenchées',
        'section_acf' => 'Attestation de Conformité Fiscale',
        'check_col' => 'Contrôle',
        'basis' => 'Fondement',
        'rule' => 'Règle',
        // helper texts
        'niu_help' => '14 caractères : P (personne physique) ou M (personne morale) + 12 chiffres + lettre.',
        'acf_help' => 'Importez l’ACF : le NIU et la référence en sont extraits automatiquement.',
        'niu_if_no_acf_help' => 'Laissez vide si vous importez une ACF ci-dessus.',
        'acf_reference_help' => 'Sinon, l’ACF la plus récente du NIU est utilisée.',
        'batch_help' => 'Un NIU par ligne (ou séparés par des virgules).',
        // notifications
        'notif_acf_imported' => 'ACF importée — :status',
        'notif_acf_detail' => 'Réf. :reference · valide jusqu’au :date',
        'notif_acf_unreadable' => 'ACF illisible',
        'notif_import_failed' => 'Import échoué',
        'notif_niu_missing' => 'NIU manquant',
        'notif_niu_missing_body' => 'Importez une ACF ou saisissez un NIU.',
        'notif_no_niu' => 'Aucun NIU valide fourni',
        'notif_batch_done' => 'Lot vérifié : :accepted acceptés / :rejected refusés',
        'notif_batch_body' => ':total NIU traités au règlement.',
        'placeholder_invalid' => '— format invalide —',
        'placeholder_dash' => '—',
        'placeholder_no_snapshot' => '— aucune (NIU non trouvé) —',
        'placeholder_no_acf' => '— non vérifiée —',
    ],
];
