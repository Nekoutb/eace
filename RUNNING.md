# Running NIU Shield locally

A Laravel 13 + Filament v5 multi-tenant app. SQLite in dev — no database server needed.

## First-time setup

```powershell
composer install
npm install
php artisan migrate           # central tables
php artisan db:seed --force   # central admin + demo tenant (demo.localhost)
php artisan tenants:migrate    # demo tenant schema
php artisan tenants:seed --class=TenantDatabaseSeeder  # demo user, suppliers, sample verifications
```

## Start the app

```powershell
php artisan serve
```

`*.localhost` subdomains resolve to 127.0.0.1 automatically — no hosts-file edit needed.

## Two panels

| Panel | URL | Login | Scope |
|---|---|---|---|
| Admin (central) | http://localhost:8000/admin | `admin@niushield.cm` / `password` | landlord — manages tenants |
| App (tenant) | http://demo.localhost:8000/app | `agent@demo.cm` / `password` | the demo tenant's workspace |

The app panel is tenant-only: it identifies the tenant by subdomain and is blocked on the central domain. The admin panel is the reverse.

## Try it

In the app panel, open **Vérifications → Vérifier un NIU** and try the seeded demo NIUs (the fake Fiscalis resolver knows these until the real connector lands):

| NIU | Verdict |
|---|---|
| `M058712345678X` | Conforme (SARL EXEMPLE, actif, régime réel) |
| `P058712345678X` | Conforme avec réserves (personne physique, régime simplifié) |
| `M111111111111A` | Suspendu |
| `M222222222222A` | Radié |
| anything else valid | Introuvable |
| `pas-un-niu` | Format invalide |

Add a montant + "TVA facturée" + paiement espèces to see the réserves rules and the FCFA exposure change.

## Quality gates

```powershell
.\vendor\bin\pest.bat        # 89 tests
.\vendor\bin\phpstan.bat analyse --memory-limit=512M   # Larastan level 6
.\vendor\bin\pint.bat        # formatting
```

## Switching to PostgreSQL (week 2)

`pdo_pgsql` is already enabled. In `.env` set `DB_CONNECTION=pgsql` and the PG credentials, remove the `.sqlite` suffix in `config/tenancy.php` (`database.suffix`), then re-run the migrate/seed steps above.
