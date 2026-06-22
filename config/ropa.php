<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Record of Processing Activities (Loi N° 2024/017 art. 29)
|--------------------------------------------------------------------------
|
| The processing activities carried out under the controller's responsibility,
| rendered on the /admin "Record of processing" page. Bilingual; edit here and run
| config:cache on the server. Keep it accurate — it is a legal register.
|
*/

return [
    'activities' => [
        [
            'name' => ['en' => 'Supplier compliance verification', 'fr' => 'Vérification de la conformité des fournisseurs'],
            'purpose' => ['en' => 'Verify a supplier’s fiscal compliance (NIU + ACF) before recording invoices or settling payments.', 'fr' => 'Vérifier la conformité fiscale d’un fournisseur (NIU + ACF) avant d’enregistrer des factures ou de régler des paiements.'],
            'basis' => ['en' => 'Performance of contract; the customer’s legal obligation.', 'fr' => 'Exécution du contrat ; obligation légale du client.'],
            'subjects' => ['en' => 'Suppliers and their representatives.', 'fr' => 'Fournisseurs et leurs représentants.'],
            'data' => ['en' => 'NIU, business/representative name, invoice and ACF contents.', 'fr' => 'NIU, nom de l’entreprise/du représentant, contenu des factures et des ACF.'],
            'recipients' => ['en' => 'Internal staff; hosting, encrypted-storage and document-reading sub-processors.', 'fr' => 'Personnel interne ; sous-traitants d’hébergement, de stockage chiffré et de lecture de documents.'],
            'transfers' => ['en' => 'Document-reading and storage providers located outside Cameroon — contractual safeguards applied.', 'fr' => 'Prestataires de lecture de documents et de stockage situés hors Cameroun — garanties contractuelles appliquées.'],
            'retention' => ['en' => 'For the audit period; evidence stored write-once.', 'fr' => 'Durée d’audit ; preuves conservées en écriture unique.'],
        ],
        [
            'name' => ['en' => 'National taxpayer register', 'fr' => 'Registre national des contribuables'],
            'purpose' => ['en' => 'Match supplier NIUs against the official register to confirm they are active.', 'fr' => 'Comparer les NIU des fournisseurs au registre officiel pour confirmer qu’ils sont actifs.'],
            'basis' => ['en' => 'Legal obligation / public-interest source (official DGI data).', 'fr' => 'Obligation légale / source d’intérêt public (données officielles de la DGI).'],
            'subjects' => ['en' => 'Registered taxpayers.', 'fr' => 'Contribuables enregistrés.'],
            'data' => ['en' => 'Name and NIU as published by the official authority.', 'fr' => 'Nom et NIU tels que publiés par l’autorité officielle.'],
            'recipients' => ['en' => 'Internal staff; hosting sub-processor.', 'fr' => 'Personnel interne ; sous-traitant d’hébergement.'],
            'transfers' => ['en' => 'Hosting located outside Cameroon — contractual safeguards applied.', 'fr' => 'Hébergement situé hors Cameroun — garanties contractuelles appliquées.'],
            'retention' => ['en' => 'Refreshed regularly; superseded snapshots replaced.', 'fr' => 'Actualisé régulièrement ; les anciens instantanés sont remplacés.'],
        ],
        [
            'name' => ['en' => 'User accounts & access', 'fr' => 'Comptes utilisateurs et accès'],
            'purpose' => ['en' => 'Authenticate users, manage roles and secure access.', 'fr' => 'Authentifier les utilisateurs, gérer les rôles et sécuriser l’accès.'],
            'basis' => ['en' => 'Performance of contract; legitimate interest (security).', 'fr' => 'Exécution du contrat ; intérêt légitime (sécurité).'],
            'subjects' => ['en' => 'Customer users and staff.', 'fr' => 'Utilisateurs clients et personnel.'],
            'data' => ['en' => 'Name, work email, role, two-factor secret, IP and access logs.', 'fr' => 'Nom, e-mail professionnel, rôle, secret à deux facteurs, IP et journaux d’accès.'],
            'recipients' => ['en' => 'Internal staff; hosting and email sub-processors.', 'fr' => 'Personnel interne ; sous-traitants d’hébergement et d’e-mail.'],
            'transfers' => ['en' => 'Hosting and email outside Cameroon — contractual safeguards applied.', 'fr' => 'Hébergement et e-mail hors Cameroun — garanties contractuelles appliquées.'],
            'retention' => ['en' => 'For the life of the account; logs kept for a limited period.', 'fr' => 'Pendant la durée du compte ; journaux conservés pour une période limitée.'],
        ],
        [
            'name' => ['en' => 'Customer support', 'fr' => 'Assistance client'],
            'purpose' => ['en' => 'Answer questions and resolve support requests (assistant + team).', 'fr' => 'Répondre aux questions et traiter les demandes d’assistance (assistant + équipe).'],
            'basis' => ['en' => 'Performance of contract; legitimate interest.', 'fr' => 'Exécution du contrat ; intérêt légitime.'],
            'subjects' => ['en' => 'Users and visitors who contact support.', 'fr' => 'Utilisateurs et visiteurs qui contactent l’assistance.'],
            'data' => ['en' => 'Messages, email, and the conversation thread.', 'fr' => 'Messages, e-mail et fil de conversation.'],
            'recipients' => ['en' => 'Internal staff; AI assistant and messaging sub-processors.', 'fr' => 'Personnel interne ; sous-traitants assistant IA et messagerie.'],
            'transfers' => ['en' => 'AI assistant and messaging located outside Cameroon — minimised and safeguarded.', 'fr' => 'Assistant IA et messagerie situés hors Cameroun — minimisés et encadrés.'],
            'retention' => ['en' => 'For as long as needed to handle and audit the request.', 'fr' => 'Aussi longtemps que nécessaire pour traiter et auditer la demande.'],
        ],
        [
            'name' => ['en' => 'Evidence storage & audit trail', 'fr' => 'Stockage des preuves et piste d’audit'],
            'purpose' => ['en' => 'Keep tamper-proof evidence of each verification for audit.', 'fr' => 'Conserver des preuves infalsifiables de chaque vérification à des fins d’audit.'],
            'basis' => ['en' => 'Legal obligation; legitimate interest.', 'fr' => 'Obligation légale ; intérêt légitime.'],
            'subjects' => ['en' => 'Suppliers and the users who performed the checks.', 'fr' => 'Fournisseurs et utilisateurs ayant effectué les contrôles.'],
            'data' => ['en' => 'Screenshots, documents, results and trusted timestamps.', 'fr' => 'Captures d’écran, documents, résultats et horodatages de confiance.'],
            'recipients' => ['en' => 'Internal staff; encrypted-storage sub-processor.', 'fr' => 'Personnel interne ; sous-traitant de stockage chiffré.'],
            'transfers' => ['en' => 'Encrypted storage located outside Cameroon — contractual safeguards applied.', 'fr' => 'Stockage chiffré situé hors Cameroun — garanties contractuelles appliquées.'],
            'retention' => ['en' => 'Long-term for audit; write-once (cannot be altered).', 'fr' => 'Long terme pour l’audit ; écriture unique (non modifiable).'],
        ],
    ],
];
