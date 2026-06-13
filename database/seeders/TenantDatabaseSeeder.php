<?php

namespace Database\Seeders;

use App\Models\ThirdParty;
use App\Models\User;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds ONE tenant's database. Runs inside tenant context via
 * `php artisan tenants:seed --class=TenantDatabaseSeeder`. Creates a staff user,
 * a few suppliers, and a handful of sample verifications across the verdict
 * spectrum so the dashboard and history are not empty on first login.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'agent@demo.cm'],
            ['name' => 'Agent Conformité', 'password' => Hash::make('password')],
        );

        $suppliers = [
            ['niu' => 'M058712345678X', 'name' => 'SARL EXEMPLE', 'type' => 'supplier'],
            ['niu' => 'M111111111111A', 'name' => 'ETS SUSPENDU', 'type' => 'supplier'],
            ['niu' => 'M222222222222A', 'name' => 'SARL RADIEE', 'type' => 'supplier'],
            ['niu' => 'P058712345678X', 'name' => 'EKANE JOSEPH BERTRAND', 'type' => 'both'],
        ];

        $service = app(VerificationService::class);

        foreach ($suppliers as $row) {
            $supplier = ThirdParty::query()->updateOrCreate(
                ['niu' => $row['niu']],
                ['name' => $row['name'], 'type' => $row['type']],
            );

            $service->verify(new VerifyRequest(
                rawInput: $row['niu'],
                thirdPartyId: $supplier->id,
                invoiceAmountXaf: 1_000_000,
                invoiceName: $row['name'],
                vatCharged: true,
            ));
        }
    }
}
