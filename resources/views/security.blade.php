@php($fr = app()->getLocale() === 'fr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fr ? 'Sécurité & protection des données' : 'Security & data protection' }} — Elite Advisors Compliance Engine</title>
    <style>
        :root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g500:#22c55e;--g600:#16a34a;
            --g700:#15803d;--g800:#166534;--g900:#14532d;--ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16)}
        *{box-sizing:border-box}
        body{margin:0;color:var(--ink);font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            line-height:1.6;background:linear-gradient(180deg,#f4fdf7,#ecfdf3)}
        .wrap{max-width:1000px;margin:0 auto;padding:2.5rem 1.25rem 4rem}
        .top{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:2rem;flex-wrap:wrap}
        .logo{font-weight:800;font-size:1.05rem;color:var(--g800)}
        .lang a{color:var(--g700);text-decoration:none;font-size:.85rem;font-weight:600;margin-left:.5rem}
        .lang a.on{color:var(--ink);text-decoration:underline}
        .hero{background:var(--g800);background:linear-gradient(150deg,var(--g700),var(--g900));color:#fff;
            border-radius:22px;padding:2.2rem 2rem;box-shadow:0 20px 50px rgba(20,83,45,.25)}
        .hero h1{margin:0 0 .5rem;font-size:1.7rem;font-weight:800}
        .hero p{margin:0;opacity:.92;max-width:62ch}
        .badges{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.2rem}
        .badge{font-size:.74rem;font-weight:700;letter-spacing:.02em;background:rgba(255,255,255,.16);
            border:1px solid rgba(255,255,255,.3);color:#fff;padding:.28rem .6rem;border-radius:999px}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:1.6rem 0}
        @media (max-width:780px){.grid{grid-template-columns:1fr}}
        .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:1.25rem 1.3rem;box-shadow:0 10px 30px rgba(20,83,45,.07)}
        .card h3{margin:0 0 .15rem;font-size:1.02rem;color:var(--g800)}
        .card .role{font-size:.76rem;color:var(--ink-soft);margin:0 0 .7rem;text-transform:uppercase;letter-spacing:.04em}
        .card ul{margin:0;padding-left:1.05rem}
        .card li{font-size:.86rem;margin:.32rem 0}
        .panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:1.4rem 1.5rem;margin-top:1rem;
            box-shadow:0 10px 30px rgba(20,83,45,.07)}
        .panel h2{margin:0 0 .6rem;font-size:1.15rem;color:var(--g800);display:flex;align-items:center;gap:.5rem}
        .panel p{margin:.3rem 0;font-size:.92rem}
        .kpis{display:flex;flex-wrap:wrap;gap:1rem;margin:.4rem 0 .2rem}
        .kpi{flex:1 1 9rem;background:var(--g50);border:1px solid var(--g200);border-radius:13px;padding:.85rem 1rem}
        .kpi b{display:block;font-size:1.45rem;font-weight:800;color:var(--g700);line-height:1.1}
        .kpi span{font-size:.8rem;color:var(--ink-soft)}
        .foot{margin-top:1.6rem;font-size:.82rem;color:var(--ink-soft);text-align:center}
        .foot a{color:var(--g700);font-weight:600;text-decoration:none}
        .note{font-size:.76rem;color:var(--ink-soft);margin-top:.8rem}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <span class="logo">Elite Advisors Compliance Engine</span>
        <span class="lang">
            <a class="{{ $fr ? 'on' : '' }}" href="{{ url('/locale/fr') }}?to=/security">FR</a>
            <a class="{{ $fr ? '' : 'on' }}" href="{{ url('/locale/en') }}?to=/security">EN</a>
        </span>
    </div>

    <section class="hero">
        <h1>{{ $fr ? 'La sécurité de vos données fiscales' : 'The security of your fiscal data' }}</h1>
        <p>{{ $fr
            ? 'Elite Advisors Compliance Engine protège vos factures, attestations et preuves de conformité avec une infrastructure certifiée, chiffrée de bout en bout et hébergée chez des prestataires audités de manière indépendante.'
            : 'Elite Advisors Compliance Engine protects your invoices, tax-clearance certificates and compliance evidence with certified, end-to-end encrypted infrastructure hosted by independently audited providers.' }}</p>
        <div class="badges">
            <span class="badge">ISO/IEC 27001:2022</span>
            <span class="badge">SOC 2 Type II</span>
            <span class="badge">GDPR / RGPD</span>
            <span class="badge">HIPAA</span>
            <span class="badge">PCI-DSS</span>
            <span class="badge">AES-256</span>
            <span class="badge">{{ $fr ? 'Immuabilité WORM' : 'WORM immutability' }}</span>
        </div>
    </section>

    <div class="grid">
        <div class="card">
            <h3>{{ $fr ? 'Domaine & DNS' : 'Domain & DNS' }}</h3>
            <p class="role">Hostinger</p>
            <ul>
                <li>{{ $fr ? 'Certifié ISO/IEC 27001:2022 (audit TÜV)' : 'ISO/IEC 27001:2022 certified (TÜV-audited)' }}</li>
                <li>{{ $fr ? 'Conforme RGPD' : 'GDPR compliant' }}</li>
                <li>{{ $fr ? 'Certificat SSL/TLS, trafic chiffré' : 'SSL/TLS certificate, encrypted traffic' }}</li>
                <li>{{ $fr ? 'Pare-feu (WAF) & anti-DDoS, surveillance 24/7' : 'Firewall (WAF) & anti-DDoS, 24/7 monitoring' }}</li>
                <li>{{ $fr ? 'Accès au panneau protégé par 2FA' : 'Control panel protected by 2FA' }}</li>
            </ul>
        </div>
        <div class="card">
            <h3>{{ $fr ? 'Serveurs' : 'Servers' }}</h3>
            <p class="role">Vultr</p>
            <ul>
                <li>{{ $fr ? 'Centres de données Tier 3 (99,982 %)' : 'Tier-3 data centers (99.982%)' }}</li>
                <li>ISO 27001 / 27017 / 27018, SOC 2+ (HIPAA)</li>
                <li>{{ $fr ? 'Chiffrement AES-256, TLS 1.3' : 'AES-256 encryption, TLS 1.3' }}</li>
                <li>{{ $fr ? 'Mitigation DDoS 10 Gbps' : '10 Gbps DDoS mitigation' }}</li>
                <li>{{ $fr ? 'Résidence des données garantie' : 'Guaranteed data residency' }}</li>
            </ul>
        </div>
        <div class="card">
            <h3>{{ $fr ? 'Stockage des documents' : 'Document storage' }}</h3>
            <p class="role">Wasabi</p>
            <ul>
                <li>ISO/IEC 27001:2022, SOC 2 Type II</li>
                <li>{{ $fr ? 'AES-256 au repos (toujours actif)' : 'AES-256 at rest (always on)' }}</li>
                <li>{{ $fr ? 'Object Lock immuable (WORM)' : 'Immutable Object Lock (WORM)' }}</li>
                <li>{{ $fr ? 'Durabilité 11 neuf (99,999999999 %)' : 'Eleven-nines durability (99.999999999%)' }}</li>
                <li>{{ $fr ? 'MFA, SSO, double approbation de suppression' : 'MFA, SSO, dual-approval deletion' }}</li>
            </ul>
        </div>
    </div>

    <section class="panel">
        <h2>🗄️ {{ $fr ? 'Capacité & conservation' : 'Capacity & retention' }}</h2>
        <div class="kpis">
            <div class="kpi"><b>{{ $fr ? 'Évolutif' : 'Scalable' }}</b><span>{{ $fr ? 'stockage dédié et sécurisé' : 'dedicated, secured storage' }}</span></div>
            <div class="kpi"><b>2 {{ $fr ? 'ans' : 'years' }}</b><span>{{ $fr ? 'accès direct (stockage chaud)' : 'direct access (hot storage)' }}</span></div>
            <div class="kpi"><b>11×9</b><span>{{ $fr ? 'durabilité des données' : 'data durability' }}</span></div>
        </div>
        <p>{{ $fr
            ? 'Vos documents restent en stockage « chaud » avec accès direct en libre-service pendant 2 ans. Au-delà, ils sont conservés de manière immuable et restitués à la demande via nos services — rien n’est jamais supprimé pendant la durée légale de conservation.'
            : 'Your documents stay in hot storage with direct self-service access for 2 years. Beyond that, they are retained immutably and retrieved on request through our services — nothing is ever deleted during the legal retention window.' }}</p>
    </section>

    <section class="panel">
        <h2>🔐 {{ $fr ? 'Chiffrement & immuabilité' : 'Encryption & immutability' }}</h2>
        <p>{{ $fr
            ? 'Chaque document est chiffré en AES-256 au repos et transmis via HTTPS/TLS. Les preuves de conformité (captures des portails de la DGI, attestations, manifestes d’audit) sont verrouillées en écriture unique / lecture multiple (Object Lock) : elles ne peuvent être ni modifiées ni supprimées pendant la durée de conservation, garantissant une piste d’audit inviolable.'
            : 'Every document is AES-256 encrypted at rest and transmitted over HTTPS/TLS. Compliance evidence (DGI portal captures, certificates, audit manifests) is write-once / read-many locked (Object Lock): it cannot be altered or deleted during the retention period, guaranteeing a tamper-proof audit trail.' }}</p>
        <p>{{ $fr
            ? 'Chaque espace client est strictement cloisonné : l’application ne lit et n’écrit que les données du locataire concerné, et aucun accès direct au stockage n’est possible — tout passe par nos services.'
            : 'Each client workspace is strictly isolated: the application only reads and writes the relevant tenant’s data, and no direct storage access is possible — everything flows through our services.' }}</p>
        <p class="note">{{ $fr
            ? 'Certifications et attestations citées telles que publiées par chaque prestataire (Hostinger, Vultr, Wasabi). Les rapports SOC 2 / ISO 27001 sont disponibles auprès des prestataires sur demande (sous NDA).'
            : 'Certifications and attestations cited as published by each provider (Hostinger, Vultr, Wasabi). SOC 2 / ISO 27001 reports are available from the providers on request (under NDA).' }}</p>
    </section>

    <p class="foot"><a href="{{ url('/app') }}">{{ $fr ? '← Retour à la connexion' : '← Back to sign in' }}</a></p>
</div>
</body>
</html>
