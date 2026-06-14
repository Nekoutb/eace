<?php

namespace Database\Seeders;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\VerificationStage;
use App\Models\AcfCertificate;
use App\Models\ThirdParty;
use App\Models\User;
use App\Services\Acf\AcfIngestionService;
use App\Services\Verification\BatchVerificationService;
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
            ['email' => 'admin'],
            ['name' => 'Agent Conformité', 'password' => Hash::make('admin')],
        );

        // A clean, authentic, in-force ACF for the active supplier — its invoice will be ACCEPTED.
        $cleanAcf = new AcfCertificate([
            'reference' => '620000000001',
            'niu' => 'M058712345678X',
            'taxpayer_name' => 'SARL EXEMPLE',
            'issued_at' => now(),
            'validity_months' => 3,
            'authenticity_status' => AcfAuthenticityStatus::Authentic,
        ]);
        $cleanAcf->recomputeValidity()->save();

        // The real EXPRESS UNION ACF (expiring soon, authenticity not yet checked) for the monitor.
        $fixture = base_path('tests/Fixtures/acf/express-union-acf.txt');
        if (is_file($fixture)) {
            app(AcfIngestionService::class)->ingestFromText(file_get_contents($fixture));
        }

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
                stage: VerificationStage::Recording,
                invoiceAmountXaf: 1_000_000,
                invoiceName: $row['name'],
                vatCharged: true,
            ));
        }

        // A settlement-stage batch so the stage filter shows both stages.
        app(BatchVerificationService::class)->verifyBatch(
            ['M058712345678X', 'M111111111111A'],
            VerificationStage::Settlement,
        );
    }
}
