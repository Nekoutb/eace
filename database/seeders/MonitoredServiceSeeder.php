<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MonitoredService;
use Illuminate\Database\Seeder;

/**
 * Seeds the known infrastructure services so the operator starts with a populated
 * dashboard. Uses firstOrCreate keyed on name, so it NEVER overwrites the operator's
 * own edits (renewal dates, amounts, auto-pay) on a re-run — it only fills gaps.
 * The operator completes each row's renewal date + amount in the admin.
 */
class MonitoredServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Vultr — Serveur de production',
                'provider' => 'Vultr',
                'category' => 'hosting',
                'powers' => 'eace.cm-ea.com (app, admin, academy)',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'auto_pay' => false,
                'health_url' => 'https://eace.cm-ea.com/app/login',
                'notes' => 'VM 45.32.150.96. La suspension pour facture impayée a causé la panne du 25/06/2026 — activer le paiement automatique en priorité.',
            ],
            [
                'name' => 'Domaine cm-ea.com',
                'category' => 'domain',
                'powers' => 'eace.cm-ea.com, eliteadvisors.cm-ea.com',
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'auto_pay' => false,
            ],
            [
                'name' => 'Wasabi — Stockage des preuves',
                'provider' => 'Wasabi',
                'category' => 'storage',
                'powers' => 'Preuves immuables (Object Lock / WORM)',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'auto_pay' => false,
            ],
            [
                'name' => 'Hostinger — Messagerie',
                'provider' => 'Hostinger',
                'category' => 'email',
                'powers' => 'contact@cm-ea.com, privacy@cm-ea.com (SMTP)',
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'auto_pay' => false,
            ],
            [
                'name' => 'Anthropic — Clé API (IA)',
                'provider' => 'Anthropic',
                'category' => 'api',
                'powers' => 'Lecture des factures + assistant support',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'auto_pay' => true,
                'notes' => 'Facturation à l’usage sur carte. Surveiller le solde / plafond.',
            ],
            [
                'name' => 'Certificat SSL (HTTPS)',
                'provider' => "Let's Encrypt",
                'category' => 'ssl',
                'powers' => 'HTTPS eace.cm-ea.com',
                'currency' => 'USD',
                'billing_cycle' => 'quarterly',
                'auto_pay' => true,
                'notes' => 'Renouvellement automatique via certbot (gratuit).',
            ],
        ];

        foreach ($services as $service) {
            $name = $service['name'];
            unset($service['name']);
            MonitoredService::query()->firstOrCreate(['name' => $name], $service);
        }
    }
}
