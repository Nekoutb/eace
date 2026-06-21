@php($fr = app()->getLocale() === 'fr')

<x-filament-panels::page>
    <div class="niu-sec">
        <style>
            .niu-sec{font-family:inherit;color:#0f231a}
            .niu-sec *{box-sizing:border-box}
            .niu-sec__hero{background:linear-gradient(150deg,#15803d,#14532d);color:#fff;border-radius:18px;
                padding:1.6rem 1.7rem;box-shadow:0 16px 40px rgba(20,83,45,.22)}
            .niu-sec__hero h2{margin:0 0 .4rem;font-size:1.3rem;font-weight:800}
            .niu-sec__hero p{margin:0;opacity:.93;max-width:64ch;font-size:.92rem}
            .niu-sec__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.9rem;margin-top:1.1rem}
            @media (max-width:900px){.niu-sec__grid{grid-template-columns:1fr 1fr}}
            @media (max-width:620px){.niu-sec__grid{grid-template-columns:1fr}}
            .niu-sec__card{background:#fff;border:1px solid rgba(21,128,61,.16);border-radius:15px;padding:1.1rem 1.2rem;
                box-shadow:0 8px 24px rgba(20,83,45,.07)}
            .niu-sec__ic{display:flex;align-items:center;justify-content:center;width:2.3rem;height:2.3rem;border-radius:11px;
                background:#f0fdf4;border:1px solid rgba(21,128,61,.18);color:#15803d;margin-bottom:.6rem}
            .niu-sec__ic svg{width:1.3rem;height:1.3rem}
            .niu-sec__card h3{margin:0 0 .25rem;font-size:1rem;font-weight:700;color:#14532d}
            .niu-sec__card p{margin:0;font-size:.86rem;color:#46685a;line-height:1.55}
            .niu-sec__panel{background:#fff;border:1px solid rgba(21,128,61,.16);border-radius:15px;padding:1.2rem 1.35rem;
                margin-top:.9rem;box-shadow:0 8px 24px rgba(20,83,45,.07)}
            .niu-sec__panel h3{margin:0 0 .5rem;font-size:1.05rem;color:#14532d;display:flex;align-items:center;gap:.45rem}
            .niu-sec__panel h3 svg{width:1.15rem;height:1.15rem;color:#15803d}
            .niu-sec__panel p{margin:.3rem 0;font-size:.88rem;color:#2f4f41;line-height:1.6}
            .niu-sec__kpis{display:flex;flex-wrap:wrap;gap:.7rem;margin:.2rem 0 .6rem}
            .niu-sec__kpi{flex:1 1 8rem;background:#f0fdf4;border:1px solid rgba(21,128,61,.18);border-radius:11px;padding:.7rem .9rem}
            .niu-sec__kpi b{display:block;font-size:1.3rem;font-weight:800;color:#15803d;line-height:1.1}
            .niu-sec__kpi span{font-size:.76rem;color:#46685a}
            /* The discreet certification strip. */
            .niu-sec__certs{margin-top:1.1rem;background:#fff;border:1px solid rgba(21,128,61,.18);border-radius:15px;
                padding:1.2rem 1.35rem;box-shadow:0 8px 24px rgba(20,83,45,.07);text-align:center}
            .niu-sec__certs h3{margin:0 0 .15rem;font-size:.95rem;font-weight:700;color:#14532d}
            .niu-sec__certs .sub{margin:0 0 .9rem;font-size:.78rem;color:#46685a}
            .niu-sec__badges{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem}
            .niu-sec__badge{font-size:.78rem;font-weight:700;letter-spacing:.01em;color:#14532d;background:#f0fdf4;
                border:1px solid rgba(21,128,61,.28);padding:.4rem .85rem;border-radius:999px;display:inline-flex;align-items:center;gap:.35rem}
            .niu-sec__badge svg{width:.95rem;height:.95rem;color:#15803d}
            .niu-sec__note{margin:.85rem 0 0;font-size:.72rem;color:#6b8278}
            .dark .niu-sec{color:#e8f6ee}
            .dark .niu-sec__card,.dark .niu-sec__panel,.dark .niu-sec__certs{background:rgba(11,28,20,.92);border-color:rgba(134,239,172,.14)}
            .dark .niu-sec__card h3,.dark .niu-sec__panel h3,.dark .niu-sec__certs h3{color:#bbf7d0}
            .dark .niu-sec__card p,.dark .niu-sec__panel p,.dark .niu-sec__kpi span,.dark .niu-sec__certs .sub{color:#9db8a9}
            .dark .niu-sec__ic,.dark .niu-sec__kpi,.dark .niu-sec__badge{background:rgba(255,255,255,.05);border-color:rgba(134,239,172,.16)}
            .dark .niu-sec__badge{color:#bbf7d0}
        </style>

        <section class="niu-sec__hero">
            <h2>{{ $fr ? 'La sécurité de vos données fiscales' : 'The security of your fiscal data' }}</h2>
            <p>{{ $fr
                ? 'Vos factures, attestations et preuves de conformité sont protégées à chaque étape — chiffrées de bout en bout, scellées de manière inviolable et hébergées sur une infrastructure auditée de façon indépendante.'
                : 'Your invoices, tax-clearance certificates and compliance evidence are protected at every step — end-to-end encrypted, tamper-proof sealed, and hosted on independently audited infrastructure.' }}</p>
        </section>

        <div class="niu-sec__grid">
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-lock-closed', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Chiffrement' : 'Encryption' }}</h3>
                <p>{{ $fr ? 'AES-256 au repos, TLS 1.3 en transit — vos documents sont chiffrés en permanence, de l’envoi au stockage.' : 'AES-256 at rest, TLS 1.3 in transit — your documents are encrypted at all times, from upload to storage.' }}</p>
            </div>
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-shield-check', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Preuves inviolables (WORM)' : 'Tamper-proof evidence (WORM)' }}</h3>
                <p>{{ $fr ? 'Chaque preuve est verrouillée en écriture unique : impossible de la modifier ou de la supprimer pendant la durée légale de conservation.' : 'Every piece of evidence is write-once locked — it cannot be altered or deleted during the legal retention window.' }}</p>
            </div>
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-clock', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Horodatage de confiance' : 'Trusted timestamps' }}</h3>
                <p>{{ $fr ? 'Chaque capture est scellée par un horodatage RFC-3161 indépendant qui prouve la date et l’heure exactes du contrôle.' : 'Each capture is sealed with an independent RFC-3161 timestamp proving the exact date and time of the check.' }}</p>
            </div>
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-rectangle-group', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Cloisonnement des espaces' : 'Workspace isolation' }}</h3>
                <p>{{ $fr ? 'Votre espace est strictement isolé : l’application ne lit et n’écrit que vos données, jamais celles d’un autre client.' : 'Your workspace is strictly isolated — the application only ever reads and writes your data, never another client’s.' }}</p>
            </div>
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-finger-print', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Accès sécurisé' : 'Secure access' }}</h3>
                <p>{{ $fr ? 'Double authentification (2FA) obligatoire, mots de passe à usage unique au premier accès et rotation forcée.' : 'Mandatory two-factor authentication (2FA), one-time first-login passwords and forced rotation.' }}</p>
            </div>
            <div class="niu-sec__card">
                <span class="niu-sec__ic">@svg('heroicon-o-server-stack', '', ['aria-hidden' => 'true'])</span>
                <h3>{{ $fr ? 'Hébergement audité' : 'Audited hosting' }}</h3>
                <p>{{ $fr ? 'Centres de données Tier-3 audités de façon indépendante, durabilité onze-neuf (99,999999999 %) et surveillance 24/7.' : 'Independently audited Tier-3 data centers, eleven-nines durability (99.999999999%) and 24/7 monitoring.' }}</p>
            </div>
        </div>

        <section class="niu-sec__panel">
            <h3>@svg('heroicon-o-circle-stack', '', ['aria-hidden' => 'true']){{ $fr ? 'Conservation & disponibilité' : 'Retention & availability' }}</h3>
            <div class="niu-sec__kpis">
                <div class="niu-sec__kpi"><b>2 {{ $fr ? 'ans' : 'years' }}</b><span>{{ $fr ? 'accès direct (stockage chaud)' : 'direct access (hot storage)' }}</span></div>
                <div class="niu-sec__kpi"><b>11×9</b><span>{{ $fr ? 'durabilité des données' : 'data durability' }}</span></div>
                <div class="niu-sec__kpi"><b>24/7</b><span>{{ $fr ? 'surveillance & mitigation DDoS' : 'monitoring & DDoS mitigation' }}</span></div>
            </div>
            <p>{{ $fr
                ? 'Vos documents restent en accès direct pendant 2 ans, puis sont conservés de manière immuable et restitués à la demande — rien n’est supprimé pendant la durée légale de conservation.'
                : 'Your documents stay in direct access for 2 years, then are retained immutably and retrieved on request — nothing is deleted during the legal retention window.' }}</p>
        </section>

        <section class="niu-sec__certs">
            <h3>{{ $fr ? 'Normes & certifications de sécurité' : 'Security standards & certifications' }}</h3>
            <p class="sub">{{ $fr ? 'L’infrastructure qui protège vos données est certifiée selon les principales normes internationales.' : 'The infrastructure protecting your data is certified to leading international standards.' }}</p>
            <div class="niu-sec__badges">
                <span class="niu-sec__badge">@svg('heroicon-m-check-badge', '', ['aria-hidden' => 'true'])ISO/IEC 27001:2022</span>
                <span class="niu-sec__badge">@svg('heroicon-m-check-badge', '', ['aria-hidden' => 'true'])SOC 2 Type II</span>
                <span class="niu-sec__badge">@svg('heroicon-m-check-badge', '', ['aria-hidden' => 'true'])GDPR / RGPD</span>
                <span class="niu-sec__badge">@svg('heroicon-m-check-badge', '', ['aria-hidden' => 'true'])HIPAA</span>
                <span class="niu-sec__badge">@svg('heroicon-m-check-badge', '', ['aria-hidden' => 'true'])PCI-DSS</span>
            </div>
            <p class="niu-sec__note">{{ $fr ? 'Certifications portées par l’infrastructure et le stockage audités qui sous-tendent la plateforme. Détails disponibles sur demande, sous accord de confidentialité.' : 'Certifications carried by the audited infrastructure and storage underpinning the platform. Details available on request, under NDA.' }}</p>
        </section>
    </div>
</x-filament-panels::page>
