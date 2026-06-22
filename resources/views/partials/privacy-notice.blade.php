@php
    $fr = app()->getLocale() === 'fr';
    $dp = config('niu.data_protection');
    $controller = $dp['controller_name'] ?? 'Elite Advisors';
    $contact = $dp['controller_contact'] ?? 'privacy@cm-ea.com';
    $address = $dp['controller_address'] ?? '';
    $version = $dp['policy_version'] ?? '1.0';
    $updated = $dp['policy_updated'] ?? '';
    $dsarDays = (int) ($dp['dsar_reply_days'] ?? 30);
@endphp
<article class="nlegal">
    <style>
        .nlegal{--g600:#16a34a;--g700:#15803d;--g800:#166534;--ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);
            width:100%;max-width:820px;margin:0 auto;background:rgba(255,255,255,.78);border:1px solid var(--line);
            border-radius:18px;padding:1.6rem 1.9rem;box-shadow:0 16px 44px rgba(21,128,61,.12);color:var(--ink);line-height:1.62}
        .nlegal h1{font-size:1.4rem;font-weight:800;color:var(--g800);margin:0 0 .2rem}
        .nlegal .meta{font-size:.8rem;color:var(--ink-soft);margin:0 0 1.3rem}
        .nlegal h2{font-size:1rem;font-weight:800;color:var(--g700);margin:1.5rem 0 .35rem}
        .nlegal p{font-size:.89rem;color:var(--ink);margin:.35rem 0}
        .nlegal ul{margin:.35rem 0;padding-left:1.2rem}
        .nlegal li{font-size:.89rem;color:var(--ink);margin:.22rem 0}
        .nlegal a{color:var(--g700);font-weight:600}
        .nlegal strong{color:var(--g800)}
        .dark .nlegal{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.16);background:rgba(16,40,28,.55)}
        .dark .nlegal h1,.dark .nlegal h2,.dark .nlegal strong{color:#bbf7d0}
        .dark .nlegal a{color:#86efac}
    </style>

    <h1>{{ $fr ? 'Politique de confidentialité' : 'Privacy notice' }}</h1>
    <p class="meta">
        {{ $fr ? 'Version' : 'Version' }} {{ $version }}@if($updated) · {{ $fr ? 'mise à jour le' : 'updated' }} {{ $updated }}@endif ·
        {{ $fr ? 'Conforme à la Loi N° 2024/017 du 23 décembre 2024 sur la protection des données à caractère personnel au Cameroun.' : 'Aligned with Cameroon Law No. 2024/017 of 23 December 2024 on personal data protection.' }}
    </p>

    <p>{{ $fr
        ? "La présente politique explique quelles données à caractère personnel nous traitons via l'Elite Advisors Compliance Engine, pourquoi, avec qui elles sont partagées, combien de temps elles sont conservées et les droits dont vous disposez."
        : 'This notice explains what personal data we process through the Elite Advisors Compliance Engine, why, with whom it is shared, how long it is kept, and the rights you have.' }}</p>

    <h2>1. {{ $fr ? 'Responsable du traitement' : 'Data controller' }}</h2>
    <p><strong>{{ $controller }}</strong>{{ $address ? ' — '.$address : '' }}.
        {{ $fr ? 'Pour toute question relative à vos données, contactez' : 'For any question about your data, contact' }}
        <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>

    <h2>2. {{ $fr ? 'Données que nous traitons' : 'Data we process' }}</h2>
    <ul>
        <li>{{ $fr ? 'Compte : nom, e-mail professionnel, rôle, mot de passe (haché), authentification à deux facteurs, dernière connexion, adresse IP et journaux d’utilisation.' : 'Account: name, work email, role, hashed password, two-factor authentication, last login, IP address and usage logs.' }}</li>
        <li>{{ $fr ? 'Contenu de votre espace de travail : le NIU et le nom des fournisseurs que vous vérifiez, les factures et attestations (ACF) numérisées (qui peuvent contenir des données de tiers) et les résultats de vérification et preuves associées.' : 'Workspace content: the NIU and name of suppliers you verify, scanned invoices and tax-clearance certificates (ACF) — which may contain third parties’ personal data — and the verification results and supporting evidence.' }}</li>
        <li>{{ $fr ? 'Registre national des contribuables (nom et NIU) issu de la source officielle (DGI), utilisé pour les contrôles de conformité.' : 'The national taxpayer register (name and NIU) sourced from the official authority (DGI), used for compliance checks.' }}</li>
        <li>{{ $fr ? 'Assistance : les messages que vous nous adressez et l’e-mail que vous fournissez.' : 'Support: the messages you send us and the email you provide.' }}</li>
    </ul>

    <h2>3. {{ $fr ? 'Finalités et base légale' : 'Purposes and lawful basis' }}</h2>
    <ul>
        <li>{{ $fr ? 'Fournir le service de vérification de la conformité fiscale de vos fournisseurs (exécution de votre contrat et respect de vos obligations légales).' : 'Provide the supplier fiscal-compliance verification service (performance of your contract and compliance with your legal obligations).' }}</li>
        <li>{{ $fr ? 'Constituer et conserver des preuves à valeur probante pour vos audits (obligation légale et intérêt légitime).' : 'Build and keep audit-grade evidence for your audits (legal obligation and legitimate interest).' }}</li>
        <li>{{ $fr ? 'Sécuriser la plateforme, prévenir la fraude et fournir l’assistance.' : 'Secure the platform, prevent fraud, and provide support.' }}</li>
    </ul>

    <h2>4. {{ $fr ? 'Destinataires et sous-traitants' : 'Recipients and processors' }}</h2>
    <p>{{ $fr
        ? "Nous ne vendons pas vos données. Elles peuvent être traitées pour notre compte par des sous-traitants, chacun lié par contrat et tenu à des garanties de sécurité, par catégories :"
        : 'We do not sell your data. It may be processed on our behalf by sub-processors, each bound by contract and security guarantees, by category:' }}</p>
    <ul>
        <li>{{ $fr ? 'Hébergement de l’application.' : 'Application hosting.' }}</li>
        <li>{{ $fr ? 'Stockage chiffré et infalsifiable des preuves.' : 'Encrypted, tamper-proof evidence storage.' }}</li>
        <li>{{ $fr ? 'Traitement par intelligence artificielle pour la lecture des documents et l’assistant de support.' : 'AI processing for document reading and the support assistant.' }}</li>
        <li>{{ $fr ? 'Envoi d’e-mails et messagerie pour le suivi des demandes d’assistance.' : 'Email delivery and messaging for support follow-up.' }}</li>
    </ul>
    <p>{{ $fr ? 'La liste détaillée des sous-traitants est disponible sur demande.' : 'A detailed list of sub-processors is available on request.' }}</p>

    <h2>5. {{ $fr ? 'Transferts internationaux' : 'International transfers' }}</h2>
    <p>{{ $fr
        ? "Certains de nos prestataires sont situés hors du Cameroun. Lorsque des données sont transférées à l’étranger, nous mettons en place des garanties contractuelles appropriées et nous nous conformons aux exigences d’autorisation de la Loi N° 2024/017 à mesure que les mécanismes de l’Autorité deviennent opérationnels."
        : 'Some of our service providers are located outside Cameroon. Where data is transferred abroad, we put appropriate contractual safeguards in place and comply with the authorisation requirements of Law No. 2024/017 as the Authority’s mechanisms become operational.' }}</p>

    <h2>6. {{ $fr ? 'Durée de conservation' : 'Retention' }}</h2>
    <p>{{ $fr
        ? "Nous conservons les données aussi longtemps que nécessaire aux finalités ci-dessus et à nos obligations légales. Les preuves de conformité sont conservées pour répondre aux besoins d’audit ; au-delà, elles peuvent être supprimées sur demande, sous réserve des durées de conservation imposées par la loi."
        : 'We keep data only as long as necessary for the purposes above and our legal obligations. Compliance evidence is retained to meet audit needs; beyond that it can be deleted on request, subject to retention periods required by law.' }}</p>

    <h2>7. {{ $fr ? 'Sécurité' : 'Security' }}</h2>
    <p>{{ $fr
        ? "Connexions chiffrées (HTTPS), chiffrement au repos, authentification à deux facteurs obligatoire, isolement strict des données entre espaces de travail, preuves en écriture unique (infalsifiables) et horodatage de confiance."
        : 'Encrypted connections (HTTPS), encryption at rest, mandatory two-factor authentication, strict data isolation between workspaces, write-once (tamper-proof) evidence and trusted timestamps.' }}</p>

    <h2>8. {{ $fr ? 'Vos droits' : 'Your rights' }}</h2>
    <p>{{ $fr ? 'Conformément à la loi, vous disposez des droits suivants :' : 'Under the law, you have the following rights:' }}</p>
    <ul>
        <li>{{ $fr ? 'Accès à vos données et information sur leur traitement.' : 'Access to your data and information about its processing.' }}</li>
        <li>{{ $fr ? 'Rectification des données inexactes ou incomplètes.' : 'Rectification of inaccurate or incomplete data.' }}</li>
        <li>{{ $fr ? 'Effacement (« droit à l’oubli »), sous réserve des obligations légales.' : 'Erasure (“right to be forgotten”), subject to legal obligations.' }}</li>
        <li>{{ $fr ? 'Opposition au traitement et à la prospection.' : 'Objection to processing and to direct marketing.' }}</li>
        <li>{{ $fr ? 'Limitation du traitement.' : 'Restriction of processing.' }}</li>
        <li>{{ $fr ? 'Portabilité de vos données dans un format lisible par machine.' : 'Portability of your data in a machine-readable format.' }}</li>
        <li>{{ $fr ? 'Ne pas faire l’objet d’une décision exclusivement automatisée et obtenir une intervention humaine.' : 'Not to be subject to a solely automated decision, and to obtain human intervention.' }}</li>
        <li>{{ $fr ? 'Retirer votre consentement à tout moment et définir des directives post-mortem.' : 'Withdraw your consent at any time and set post-mortem directives.' }}</li>
    </ul>
    <p>{{ $fr ? 'Pour exercer ces droits, écrivez à' : 'To exercise these rights, write to' }}
        <a href="mailto:{{ $contact }}">{{ $contact }}</a>{{ $fr ? " ; nous répondons dans un délai de $dsarDays jours." : ". We respond within $dsarDays days." }}</p>

    <h2>9. {{ $fr ? 'Réclamation' : 'Complaints' }}</h2>
    <p>{{ $fr
        ? "Vous avez le droit d’introduire une réclamation auprès de l’Autorité de protection des données à caractère personnel du Cameroun."
        : 'You have the right to lodge a complaint with Cameroon’s Personal Data Protection Authority.' }}</p>

    <h2>10. {{ $fr ? 'Modifications' : 'Changes' }}</h2>
    <p>{{ $fr
        ? "Nous pouvons mettre à jour la présente politique ; la version et la date en tête de page indiquent la dernière mise à jour."
        : 'We may update this notice; the version and date at the top indicate the latest update.' }}</p>
</article>
