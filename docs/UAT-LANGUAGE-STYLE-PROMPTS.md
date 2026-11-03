# UAT language style: ready-to-use prompts (EN / FR)

There are two prompts here, each in English and in French:

- **Prompt A (for an AI assistant).** It writes the *UAT Language Style Guide*:
  the wording rules, keywords and templates that every user acceptance test (UAT)
  document must follow, in English and in French.
- **Prompt B (for the developer).** It turns that style guide into files in this
  repository: guides, templates, glossary, sample test cases and a review
  checklist. All UI labels come from `lang/en` and `lang/fr`.

Copy everything inside the fenced block, and only that.

---

## Prompt A: AI writes the UAT Language Style Guide (English version)

```text
ROLE
You are a senior UAT lead and a plain-language technical writer. You know
ISO/IEC/IEEE 29119-3 (test documentation), ISTQB terminology, ISO 24495-1
(plain language), Gherkin (Given/When/Then) and the Microsoft English and French
style guides. You write for business users, not for testers or developers.

CONTEXT
Product: NIU Shield, a bilingual (English/French) web application used in
Cameroon by companies to verify a vendor's tax identity before accepting and
paying an invoice. Four checks are run: (1) ACF document authenticity via its QR
code (DGI Harmony2), (2) live NIU status (Actif / Suspendu / Radié), (3) NIU
existence in the monthly national taxpayer file, (4) ACF validity date
(issue date + 3 months). Two stages: Invoice recording (Enregistrement, one
invoice at a time) and Invoice settlement (Règlement, a batch of NIUs).
Roles: client Administrator, Invoice-recording user, Invoice-settling user
(portal /app), and the platform operator (console /admin).
UAT participants are finance, accounting and procurement staff. Many are not
technical, some read English as a second language, and some read French as a
second language.

GOAL
Write the "UAT Language Style Guide": one language style that every UAT
document must use, so that any participant knows at once (a) what is required of
them, (b) exactly how to do each step, (c) what they should see, and (d) how to
record a Pass, Fail or Blocked result. Write it in ENGLISH and in FRENCH. Do not
translate word for word: write each version natively, with the same rules and the
same structure.

FIRST, COMPARE STYLES AND CHOOSE ONE
Briefly compare these styles for UAT: free prose; numbered imperative steps
with expected results; Gherkin Given/When/Then; a hybrid. Score each against
these criteria: ease of understanding, speed of execution, unambiguous pass/fail,
easy defect reporting, and translation quality between EN and FR. Recommend
ONE style and justify it in at most 150 words. (Expected direction: a scenario
summary in Given/When/Then, followed by numbered imperative steps, each with one
observable expected result. Confirm this or argue against it.)

THE GUIDE MUST CONTAIN
1. Purpose and audience, in 3 sentences or fewer.
2. Core writing rules, each with a "Do" and a "Don't" example in EN and FR:
   - One action per step, and start the step with the action verb.
   - Imperative mood. EN: "Click Save". FR: polite imperative (vouvoiement),
     "Cliquez sur Enregistrer". Never use "tu". Never use the infinitive in steps.
   - Sentences of 20 words or fewer. Steps of 2 lines or fewer. Target reading
     level CEFR B1.
   - Active voice. Present tense. No double negatives. No "should/may/might" in
     expected results: state facts ("The status shows Actif").
   - Show the UI labels exactly as they appear on screen, in bold, in the
     language of the test (**Save** / **Enregistrer**). Put test data in code
     format (`M020100021296Z`).
   - Every expected result must be observable and checkable: something visible,
     a message, a status, a value or a file. Never write "works correctly".
   - Each step says where it happens (screen, menu or tab) before the action.
   - Avoid jargon. Define every acronym the first time it appears (NIU, ACF, DGI,
     UAT/RUA).
   - Inclusive and neutral wording. No blame wording in defect reports.
3. A CONTROLLED VOCABULARY of action verbs (about 25), with the single approved
   EN term, the single approved FR term, and banned synonyms. Examples: Open/Ouvrez,
   Click/Cliquez sur, Select/Sélectionnez, Enter/Saisissez, Upload/Téléversez
   (or Importez: choose one and justify), Scan/Scannez, Check/Vérifiez,
   Confirm/Confirmez, Download/Téléchargez, Sign in/Connectez-vous,
   Sign out/Déconnectez-vous, Wait until/Attendez que, Note/Notez,
   Take a screenshot/Faites une capture d'écran.
4. A UAT KEYWORD GLOSSARY in a two-column EN | FR table, with a one-line
   definition each. It must include at least: User Acceptance Testing (UAT) |
   Recette utilisateur / Tests d'acceptation utilisateur (TAU); test plan | plan
   de recette; test scenario | scénario de test; test case | cas de test; test
   step | étape; precondition | prérequis; test data | jeu de données;
   expected result | résultat attendu; actual result | résultat obtenu;
   acceptance criteria | critères d'acceptation; entry/exit criteria | critères
   d'entrée/de sortie; Pass | Réussi (Conforme); Fail | Échec (Non conforme);
   Blocked | Bloqué; Not run | Non exécuté; defect | anomalie; severity
   (Critical/Major/Minor/Cosmetic) | gravité (Bloquante/Majeure/Mineure/
   Cosmétique); priority | priorité; evidence | preuve; screenshot | capture
   d'écran; retest | nouveau test; regression | régression; test environment |
   environnement de recette; tester | testeur / testeuse; test lead |
   responsable de recette; sign-off | validation / PV de recette; workaround |
   contournement. Add the product terms: NIU, ACF, Invoice recording |
   Enregistrement, Invoice settlement | Règlement, Accept | Accepter,
   Reject | Rejeter, Active | Actif, Suspended | Suspendu, Struck off | Radié,
   override | dérogation, justification | justification.
   Each concept gets ONE term only. Mark the term that participants will see on
   screen.
5. Gherkin keywords in both languages: Feature/Fonctionnalité,
   Scenario/Scénario, Given/Étant donné (or Soit), When/Quand, Then/Alors,
   And/Et, But/Mais. Give rules: at most one "When" per scenario, and "Then"
   only describes observable outcomes.
6. Formatting and typography rules:
   - EN: dates DD/MM/YYYY; amounts "1,250,000 FCFA".
   - FR: dates JJ/MM/AAAA; amounts "1 250 000 FCFA" with a non-breaking space;
     a non-breaking space before : ; ! ?; quotation marks « »; accents on
     capital letters (É, À).
   - Numbered steps. A test case ID format (for example UAT-REC-001 for
     recording, UAT-REG-001 for settlement, UAT-ADM-001 for administration).
   - Colour or icon conventions for Pass, Fail and Blocked that also work when
     printed in black and white.
7. TEMPLATES, filled in EN and FR:
   a. Test case: ID, title (a verb plus the business outcome), role, stage,
      priority, preconditions, test data, Given/When/Then summary, a steps table
      (No. | Action | Expected result | Pass/Fail/Blocked | Comment/Evidence),
      overall result, tester, date.
   b. Defect report: ID, test case ID, title (what is wrong, where), steps to
      reproduce, expected vs actual, severity, evidence, environment, date.
   c. Participant briefing (one page): what UAT is, what you must do, how to
      record results, who to contact, deadlines.
   d. Sign-off statement (PV de recette).
8. WORKED EXAMPLES, in EN and FR. Write at least 3 complete test cases in the
   final style:
   - Record an invoice whose ACF is valid and NIU is Actif → Accept.
   - Record an invoice whose ACF has expired → Reject with the reason shown.
   - Settle a batch of 5 NIUs, one of them Suspendu → ranked report shows it.
   Use placeholder labels in [square brackets] wherever the exact screen label is
   not known, so that the developer can replace them with the real labels.
9. A REVIEW CHECKLIST (about 15 yes/no checks) that a reviewer uses before a
   test case is given to participants.
10. A "before/after" section: rewrite 5 badly worded steps into the approved
    style, in EN and FR.

OUTPUT FORMAT
Markdown. Put the English guide first, then the French guide ("Guide de style
rédactionnel de la recette utilisateur"), with identical section numbering. Use
tables for vocabulary, glossary and templates. Do not add filler. At the end,
list any assumptions you made and any open questions for the product owner.

QUALITY BAR
Before you answer, check every example step against your own rules. Rewrite any
step that breaks a rule: a step with more than one action, a step without a
verb, an unobservable expected result, an inconsistent term, a "tu" form, or a
missing non-breaking space in French.
```

---

## Prompt A : l'IA rédige le guide de style de la recette (version française)

```text
RÔLE
Vous êtes responsable de recette senior et rédacteur technique spécialisé en
langage clair. Vous maîtrisez l'ISO/IEC/IEEE 29119-3 (documentation de test), la
terminologie ISTQB, l'ISO 24495-1 (langage clair et simple), le Gherkin
(Étant donné / Quand / Alors) et les guides de style Microsoft en français et en
anglais. Vous écrivez pour des utilisateurs métier, pas pour des testeurs ni pour
des développeurs.

CONTEXTE
Produit : NIU Shield, une application web bilingue (français/anglais) utilisée au
Cameroun par les entreprises pour vérifier l'identité fiscale d'un fournisseur
avant d'accepter et de payer une facture. Quatre contrôles sont effectués :
(1) l'authenticité de l'ACF via son QR code (DGI Harmony2), (2) le statut du NIU
en direct (Actif / Suspendu / Radié), (3) l'existence du NIU dans le fichier
national mensuel des contribuables, (4) la validité de l'ACF (date d'émission
+ 3 mois). Deux étapes : l'Enregistrement (une facture à la fois) et le
Règlement (un lot de NIU).
Rôles : Administrateur client, utilisateur Enregistrement des factures,
utilisateur Règlement des factures (portail /app) et l'opérateur de la plateforme
(console /admin).
Les participants à la recette sont des agents des services finance, comptabilité
et achats. Beaucoup ne sont pas techniciens, et certains lisent le français ou
l'anglais comme seconde langue.

OBJECTIF
Rédigez le « Guide de style rédactionnel de la recette utilisateur » : un style
unique, imposé à tous les documents de recette, qui permet à chaque participant
de savoir tout de suite (a) ce que l'on attend de lui, (b) comment exécuter
précisément chaque étape, (c) ce qu'il doit voir à l'écran et (d) comment
consigner un résultat Réussi, Échec ou Bloqué. Rédigez-le en FRANÇAIS et en
ANGLAIS. Ne traduisez pas mot à mot : rédigez chaque version directement dans sa
langue, avec les mêmes règles et la même structure.

COMMENCEZ PAR COMPARER LES STYLES ET EN CHOISIR UN
Comparez brièvement ces styles pour la recette : texte libre ; étapes numérotées
à l'impératif avec résultat attendu ; Gherkin Étant donné / Quand / Alors ;
un style hybride. Notez chacun selon ces critères : facilité de compréhension,
rapidité d'exécution, verdict Réussi/Échec sans ambiguïté, facilité de
signalement des anomalies et qualité de la traduction FR/EN. Recommandez UN seul
style et justifiez-le en 150 mots au maximum. (Orientation attendue : un résumé
du scénario en Étant donné / Quand / Alors, suivi d'étapes numérotées à
l'impératif, avec un seul résultat attendu observable par étape. Confirmez cette
orientation ou contestez-la.)

LE GUIDE DOIT CONTENIR
1. L'objet et le public visé, en 3 phrases au maximum.
2. Les règles d'écriture essentielles, chacune avec un exemple « À faire » et un
   exemple « À éviter » en FR et en EN :
   - Une seule action par étape, et l'étape commence par le verbe d'action.
   - Impératif de politesse (vouvoiement) : « Cliquez sur Enregistrer ».
     Jamais de tutoiement, jamais d'infinitif dans les étapes. EN : « Click Save ».
   - Phrases de 20 mots au maximum, étapes de 2 lignes au maximum. Niveau de
     lecture visé : CECRL B1.
   - Voix active, présent de l'indicatif, pas de double négation. Pas de
     « devrait / pourrait » dans les résultats attendus : énoncez un fait
     (« Le statut affiche Actif »).
   - Les libellés de l'interface apparaissent exactement comme à l'écran, en gras,
     dans la langue du test (**Enregistrer** / **Save**). Les données de test sont
     en police code (`M020100021296Z`).
   - Chaque résultat attendu est observable et vérifiable : un élément affiché, un
     message, un statut, une valeur ou un fichier. Jamais « fonctionne
     correctement ».
   - Chaque étape indique le lieu de l'action (écran, menu ou onglet) avant
     l'action elle-même.
   - Pas de jargon. Chaque sigle est développé à sa première occurrence (NIU, ACF,
     DGI, RUA/UAT).
   - Formulation neutre et inclusive. Aucun ton accusateur dans les fiches
     d'anomalie.
3. Un VOCABULAIRE CONTRÔLÉ d'environ 25 verbes d'action, avec un seul terme
   approuvé en FR, un seul en EN, et les synonymes interdits. Exemples :
   Ouvrez/Open, Cliquez sur/Click, Sélectionnez/Select, Saisissez/Enter,
   Téléversez ou Importez/Upload (choisissez-en un et justifiez), Scannez/Scan,
   Vérifiez/Check, Confirmez/Confirm, Téléchargez/Download,
   Connectez-vous/Sign in, Déconnectez-vous/Sign out, Attendez que/Wait until,
   Notez/Note, Faites une capture d'écran/Take a screenshot.
4. Un GLOSSAIRE DES MOTS-CLÉS DE LA RECETTE sous forme de tableau FR | EN, avec
   une définition d'une ligne pour chaque terme. Il contient au minimum : Recette
   utilisateur / Tests d'acceptation utilisateur (TAU) | User Acceptance Testing
   (UAT) ; plan de recette | test plan ; scénario de test | test scenario ;
   cas de test | test case ; étape | test step ; prérequis | precondition ;
   jeu de données | test data ; résultat attendu | expected result ;
   résultat obtenu | actual result ; critères d'acceptation | acceptance
   criteria ; critères d'entrée/de sortie | entry/exit criteria ;
   Réussi (Conforme) | Pass ; Échec (Non conforme) | Fail ; Bloqué | Blocked ;
   Non exécuté | Not run ; anomalie | defect ; gravité
   (Bloquante/Majeure/Mineure/Cosmétique) | severity (Critical/Major/Minor/
   Cosmetic) ; priorité | priority ; preuve | evidence ; capture d'écran |
   screenshot ; nouveau test | retest ; régression | regression ; environnement
   de recette | test environment ; testeur / testeuse | tester ; responsable de
   recette | test lead ; validation / PV de recette | sign-off ;
   contournement | workaround. Ajoutez les termes du produit : NIU, ACF,
   Enregistrement | Invoice recording, Règlement | Invoice settlement,
   Accepter | Accept, Rejeter | Reject, Actif | Active, Suspendu | Suspended,
   Radié | Struck off, dérogation | override, justification | justification.
   Un seul terme par notion. Signalez le terme que les participants voient à
   l'écran.
5. Les mots-clés Gherkin dans les deux langues : Fonctionnalité/Feature,
   Scénario/Scenario, Étant donné (ou Soit)/Given, Quand/When, Alors/Then,
   Et/And, Mais/But. Donnez les règles : un seul « Quand » par scénario, et
   « Alors » ne décrit que des résultats observables.
6. Les règles de mise en forme et de typographie :
   - FR : dates JJ/MM/AAAA ; montants « 1 250 000 FCFA » avec espace insécable ;
     espace insécable avant : ; ! ? ; guillemets « » ; accents sur les capitales
     (É, À).
   - EN : dates DD/MM/YYYY ; montants « 1,250,000 FCFA ».
   - Étapes numérotées. Un format d'identifiant (par exemple UAT-REC-001 pour
     l'Enregistrement, UAT-REG-001 pour le Règlement, UAT-ADM-001 pour
     l'administration).
   - Des conventions de couleur ou de pictogramme pour Réussi, Échec et Bloqué,
     lisibles aussi sur une impression en noir et blanc.
7. Des MODÈLES remplis, en FR et en EN :
   a. Cas de test : identifiant, titre (un verbe et le résultat métier), rôle,
      étape, priorité, prérequis, jeu de données, résumé Étant donné / Quand /
      Alors, un tableau des étapes (N° | Action | Résultat attendu |
      Réussi/Échec/Bloqué | Commentaire/Preuve), résultat global, testeur, date.
   b. Fiche d'anomalie : identifiant, identifiant du cas de test, titre (ce qui
      ne va pas, et où), étapes pour reproduire, résultat attendu et résultat
      obtenu, gravité, preuve, environnement, date.
   c. Note d'information aux participants (une page) : ce qu'est la recette, ce
      que vous devez faire, comment consigner les résultats, qui contacter, les
      échéances.
   d. Procès-verbal (PV) de recette.
8. Des EXEMPLES COMPLETS, en FR et en EN. Rédigez au moins 3 cas de test dans le
   style final :
   - Enregistrer une facture dont l'ACF est valide et le NIU Actif → Accepter.
   - Enregistrer une facture dont l'ACF est expirée → Rejeter, avec le motif
     affiché.
   - Régler un lot de 5 NIU dont un Suspendu → le rapport classé le fait
     apparaître.
   Mettez entre [crochets] tout libellé d'écran inconnu, pour que le développeur
   le remplace par le libellé réel.
9. Une GRILLE DE RELECTURE d'environ 15 points oui/non, utilisée avant de remettre
   un cas de test aux participants.
10. Une section « Avant / Après » : réécrivez 5 étapes mal rédigées dans le style
    approuvé, en FR et en EN.

FORMAT DE SORTIE
Markdown. Le guide en français d'abord, puis le guide en anglais (« UAT Language
Style Guide »), avec une numérotation identique. Utilisez des tableaux pour le
vocabulaire, le glossaire et les modèles. Pas de remplissage. À la fin, listez
vos hypothèses et vos questions ouvertes pour le responsable produit.

EXIGENCE DE QUALITÉ
Avant de répondre, vérifiez chaque étape de vos exemples au regard de vos propres
règles. Réécrivez toute étape qui enfreint une règle : une étape avec plus d'une
action, une étape sans verbe, un résultat attendu non observable, un terme
incohérent, un tutoiement, ou une espace insécable manquante en français.
```

---

## Prompt B: Developer implements the UAT language style (English version)

```text
TASK
Add the "UAT Language Style Guide" to the NIU Shield repository (Laravel +
Filament, bilingual EN/FR). Produce ready-to-use UAT material that follows the
guide, in English and in French. All screen labels must be the real ones from
the application.

INPUT
- The UAT Language Style Guide (EN + FR), produced from Prompt A. It is
  attached, or saved at docs/uat/_source/style-guide-draft.md.
- Translation files: lang/en/*.php, lang/fr/*.php and lang/fr.json. These are
  the single source of truth for screen labels.
- Business context: CORE-EXECUTION-PLAN.md (the 4 checks, the Enregistrement
  and Règlement stages), docs/ACCESS-CONTROL.md (roles, /app and /admin),
  PROJECT-TRACKER.md (features that are already live).

DELIVERABLES (create the docs/uat/ folder)
1. docs/uat/STYLE-GUIDE.en.md and docs/uat/STYLE-GUIDE.fr.md: the final guide,
   cleaned up, with identical section numbering in both files.
2. docs/uat/GLOSSARY.md: one EN | FR | definition table. It holds the UAT
   keywords plus the product terms. Every term that is shown on screen MUST match
   the exact string in lang/en or lang/fr. Next to each such term, note the
   translation key it comes from (for example app.stage.recording).
3. docs/uat/templates/: test-case.en.md, test-case.fr.md, defect-report.en.md,
   defect-report.fr.md, participant-briefing.en.md,
   participant-briefing.fr.md, sign-off.en.md, sign-off.fr.md
   (sign-off.fr.md is the PV de recette). Also export an execution tracker as
   UTF-8 CSV with a BOM (uat-tracker.en.csv, uat-tracker.fr.csv), so that Excel
   opens it with the accents intact. Columns: ID, Title, Role, Stage, Priority,
   Preconditions, Test data, Step No., Action, Expected result, Result, Comment,
   Evidence, Tester, Date.
4. docs/uat/cases/: complete test cases in EN and FR for the live features. At a
   minimum, cover:
   - REC (Invoice recording): valid ACF + Actif NIU → accept; expired ACF →
     reject; NIU Suspendu or Radié → reject; forged or unreadable QR; NIU not in
     the monthly file; client address in a different word order still matches;
     operator override with a mandatory justification on a rejection; replacing
     an ACF overwrites the previous one.
   - REG (Invoice settlement): batch with mixed statuses → ranked report; ACF
     that expired between recording and settlement is flagged.
   - ADM (Administrator / operator): add a user with an optional phone number;
     check role separation (a client user cannot open /admin).
   Use the ID format UAT-REC-###, UAT-REG-###, UAT-ADM-###. Keep the same ID
   for the EN and FR versions of a case.
5. docs/uat/REVIEW-CHECKLIST.md: the yes/no review grid, in EN and FR.
6. A short "UAT documentation" section in RUNNING.md or WORKFLOW.md that links to
   docs/uat/.

RULES TO ENFORCE
- One action per step. Imperative mood: EN "Click", FR "Cliquez sur", with
  vouvoiement. No infinitives and no "tu".
- Each step has exactly one observable expected result. Never write "works
  correctly", "fonctionne correctement", "should" or "devrait".
- UI labels are in bold and copied exactly from lang/*; test data is in
  `code` format.
- Replace every [placeholder] label from the AI draft with the real label. If a
  label does not exist in lang/*, do NOT invent one. List it under "Open
  questions" in docs/uat/README.md.
- French typography: a non-breaking space (U+00A0, or U+202F before ; ! ?)
  before : ; ! ?; quotation marks « »; amounts "1 250 000 FCFA"; dates
  JJ/MM/AAAA. English: "1,250,000 FCFA", DD/MM/YYYY.
- Test data must be fictitious or anonymised. Do not use real company names or
  real NIUs in the cases. Create a clearly fake data set, and list it in
  docs/uat/TEST-DATA.md.

OPTIONAL (only if time allows)
Add scripts/uat-lint.php (or an npm script) that scans docs/uat/cases/*.md and
flags: steps with more than one action verb, banned words ("should",
"devrait", "fonctionne correctement", "works correctly", "tu "), a missing
expected result, and bold labels that are not found in lang/en or lang/fr.
Run it in CI as a warning, not as a failure.

ACCEPTANCE CRITERIA (Definition of Done)
- Every file exists in EN and FR, with identical structure and IDs.
- 100% of the bold UI labels in the cases match a string in lang/*, or are
  listed under "Open questions".
- A non-technical colleague can run UAT-REC-001 alone, without asking a
  question. Do one dry run, and note the result in docs/uat/README.md.
- The review checklist passes on every case.
- Documentation only: no application code changes, and the existing test suite
  (Pest, Pint, Larastan) stays green.
- Commit on a feature branch with a clear message, and open a pull request.
```

---

## Prompt B : le développeur met en œuvre le style de la recette (version française)

```text
MISSION
Intégrez le « Guide de style rédactionnel de la recette utilisateur » au dépôt
NIU Shield (Laravel + Filament, bilingue FR/EN). Produisez des supports de recette
prêts à l'emploi, conformes au guide, en français et en anglais. Tous les
libellés d'écran doivent être les libellés réels de l'application.

ENTRÉES
- Le guide de style (FR + EN) produit à partir du prompt A. Il est joint, ou
  enregistré dans docs/uat/_source/style-guide-draft.md.
- Les fichiers de traduction : lang/fr/*.php, lang/fr.json et lang/en/*.php. Ils
  sont la seule source de vérité pour les libellés d'écran.
- Le contexte métier : CORE-EXECUTION-PLAN.md (les 4 contrôles, les étapes
  Enregistrement et Règlement), docs/ACCESS-CONTROL.md (les rôles, /app et
  /admin), PROJECT-TRACKER.md (les fonctionnalités déjà en production).

LIVRABLES (créez le dossier docs/uat/)
1. docs/uat/STYLE-GUIDE.fr.md et docs/uat/STYLE-GUIDE.en.md : le guide final,
   mis au propre, avec une numérotation identique dans les deux fichiers.
2. docs/uat/GLOSSARY.md : un seul tableau FR | EN | définition. Il contient les
   mots-clés de la recette et les termes du produit. Tout terme affiché à l'écran
   DOIT correspondre exactement à la chaîne de lang/fr ou lang/en. À côté de
   chacun de ces termes, notez la clé de traduction d'origine (par exemple
   app.stage.recording).
3. docs/uat/templates/ : test-case.fr.md, test-case.en.md, defect-report.fr.md,
   defect-report.en.md, participant-briefing.fr.md,
   participant-briefing.en.md, sign-off.fr.md (le PV de recette) et
   sign-off.en.md. Exportez aussi un tableau de suivi au format CSV UTF-8 avec BOM
   (uat-tracker.fr.csv, uat-tracker.en.csv), pour qu'Excel l'ouvre avec les
   accents intacts. Colonnes : ID, Titre, Rôle, Étape, Priorité, Prérequis, Jeu de
   données, N° d'étape, Action, Résultat attendu, Résultat, Commentaire, Preuve,
   Testeur, Date.
4. docs/uat/cases/ : des cas de test complets, en FR et en EN, pour les
   fonctionnalités en production. Couvrez au minimum :
   - REC (Enregistrement) : ACF valide + NIU Actif → acceptation ; ACF expirée →
     rejet ; NIU Suspendu ou Radié → rejet ; QR falsifié ou illisible ; NIU absent
     du fichier mensuel ; adresse du client dans un ordre de mots différent,
     toujours reconnue ; dérogation de l'opérateur avec justification obligatoire
     sur un rejet ; le remplacement d'une ACF écrase la précédente.
   - REG (Règlement) : lot aux statuts mixtes → rapport classé ; ACF expirée
     entre l'enregistrement et le règlement, signalée.
   - ADM (Administrateur / opérateur) : ajout d'un utilisateur avec un numéro de
     téléphone facultatif ; vérification de la séparation des rôles (un
     utilisateur client ne peut pas ouvrir /admin).
   Utilisez le format d'identifiant UAT-REC-###, UAT-REG-###, UAT-ADM-###. Gardez
   le même identifiant pour les versions FR et EN d'un cas.
5. docs/uat/REVIEW-CHECKLIST.md : la grille de relecture oui/non, en FR et en EN.
6. Une courte section « Documentation de recette » dans RUNNING.md ou
   WORKFLOW.md, avec un lien vers docs/uat/.

RÈGLES À FAIRE RESPECTER
- Une seule action par étape. Impératif de politesse : FR « Cliquez sur », EN
  « Click ». Pas d'infinitif, pas de tutoiement.
- Chaque étape a exactement un résultat attendu observable. Jamais
  « fonctionne correctement », « works correctly », « devrait » ni « should ».
- Les libellés d'interface sont en gras et copiés exactement depuis lang/* ; les
  données de test sont en format `code`.
- Remplacez chaque libellé [entre crochets] du brouillon de l'IA par le libellé
  réel. Si un libellé n'existe pas dans lang/*, NE L'INVENTEZ PAS : listez-le dans
  la section « Questions ouvertes » de docs/uat/README.md.
- Typographie française : espace insécable (U+00A0, ou U+202F avant ; ! ?) avant
  : ; ! ? ; guillemets « » ; montants « 1 250 000 FCFA » ; dates JJ/MM/AAAA.
  Anglais : « 1,250,000 FCFA », DD/MM/YYYY.
- Les données de test sont fictives ou anonymisées. N'utilisez ni vrais noms
  d'entreprise ni vrais NIU dans les cas. Créez un jeu de données manifestement
  fictif, et listez-le dans docs/uat/TEST-DATA.md.

OPTIONNEL (si le temps le permet)
Ajoutez scripts/uat-lint.php (ou un script npm) qui analyse
docs/uat/cases/*.md et signale : les étapes avec plus d'un verbe d'action, les
mots interdits (« devrait », « should », « fonctionne correctement », « works
correctly », « tu »), un résultat attendu manquant, et les libellés en gras
absents de lang/fr et de lang/en. Exécutez-le en CI comme un avertissement, pas
comme un échec.

CRITÈRES D'ACCEPTATION (définition du « terminé »)
- Chaque fichier existe en FR et en EN, avec la même structure et les mêmes
  identifiants.
- 100 % des libellés d'interface en gras dans les cas correspondent à une chaîne
  de lang/*, ou figurent dans la section « Questions ouvertes ».
- Un collègue non technicien exécute UAT-REC-001 seul, sans poser de question.
  Faites un essai à blanc et notez le résultat dans docs/uat/README.md.
- La grille de relecture est validée sur chaque cas.
- Documentation uniquement : aucun changement du code applicatif, et la suite de
  tests existante (Pest, Pint, Larastan) reste au vert.
- Committez sur une branche dédiée avec un message clair, et ouvrez une pull
  request.
```

---

## How to use them

1. Paste **Prompt A** (EN or FR) into your AI assistant. Review the draft guide,
   especially the recommended style and the open questions.
2. Save the draft as `docs/uat/_source/style-guide-draft.md`, or attach it.
3. Hand **Prompt B** to the developer, in whichever language they prefer.
