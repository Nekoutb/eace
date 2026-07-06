<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Models\AccountAssignment;
use App\Models\Firm;
use App\Models\FirmMember;
use App\Services\Firm\FirmException;
use App\Services\Firm\FirmMembershipService;
use Illuminate\Console\Command;

/**
 * Operator onboarding for accounting firms (the agreed model: firms are onboarded
 * by the operator). Create a firm, add team members, create client sub-accounts,
 * and assign members to accounts — all via FirmMembershipService, so the same
 * tested rules (caps, isolation, credential mirroring) apply. A polished /admin
 * console will follow; this is the CLI in the meantime.
 */
class FirmManage extends Command
{
    protected $signature = 'firm:manage
        {action : create-firm | add-member | add-account | assign | list}
        {--firm= : firm id}
        {--name= : firm name (create-firm) OR client name (add-account)}
        {--max=25 : max sub-accounts (create-firm)}
        {--quota= : storage quota MB (create-firm default / add-account)}
        {--member= : member full name (add-member)}
        {--email= : member email (add-member, assign)}
        {--role= : firm role for add-member (power_user|member) or account role for assign (admin|recorder|settler)}
        {--password= : member password (add-member; omit for a one-time temp password)}
        {--niu= : client NIU (add-account)}
        {--tenant= : sub-account tenant id (assign)}';

    protected $description = 'Manage accounting firms: firms, members, client sub-accounts and assignments';

    public function handle(FirmMembershipService $svc): int
    {
        try {
            return match ((string) $this->argument('action')) {
                'create-firm' => $this->createFirm($svc),
                'add-member' => $this->addMember($svc),
                'add-account' => $this->addAccount($svc),
                'assign' => $this->assign($svc),
                'list' => $this->list(),
                default => $this->failWith('Unknown action. Use: create-firm | add-member | add-account | assign | list'),
            };
        } catch (FirmException $e) {
            return $this->failWith($e->getMessage());
        }
    }

    private function createFirm(FirmMembershipService $svc): int
    {
        $name = $this->req('name');
        $firm = $svc->createFirm($name, (int) $this->option('max'), (int) ($this->option('quota') ?: 1024));
        $this->info("Firm created: #{$firm->id} — {$firm->name} (cap {$firm->max_sub_accounts}).");

        return self::SUCCESS;
    }

    private function addMember(FirmMembershipService $svc): int
    {
        $firm = $this->firm();
        $role = $this->option('role') === 'power_user' ? FirmRole::PowerUser : FirmRole::Member;
        $added = $svc->addMember($firm, $this->req('member'), $this->req('email'), $role, $this->option('password') ?: null);

        $this->info("Member added: {$added->member->email} ({$role->value}) to firm #{$firm->id}.");
        if ($added->temporaryPassword !== null) {
            $this->warn("One-time password: {$added->temporaryPassword}");
        }

        return self::SUCCESS;
    }

    private function addAccount(FirmMembershipService $svc): int
    {
        $firm = $this->firm();
        $tenant = $svc->createSubAccount($firm, $this->req('name'), $this->option('niu') ?: null, $this->option('quota') !== null ? (int) $this->option('quota') : null);
        $this->info("Sub-account created: {$tenant->getTenantKey()} — {$tenant->name} (firm #{$firm->id}).");

        return self::SUCCESS;
    }

    private function assign(FirmMembershipService $svc): int
    {
        $member = FirmMember::query()->where('email', mb_strtolower(trim($this->req('email'))))->firstOrFail();
        $role = UserRole::tryFrom((string) $this->option('role')) ?? UserRole::Admin;
        $svc->assign($member, $this->req('tenant'), $role);
        $this->info("Assigned {$member->email} to {$this->option('tenant')} as {$role->value}.");

        return self::SUCCESS;
    }

    private function list(): int
    {
        foreach (Firm::query()->orderBy('id')->get() as $firm) {
            $this->line("<info>Firm #{$firm->id}</info> — {$firm->name} (".$firm->subAccountCount().'/'.$firm->max_sub_accounts.' sub-accounts)');
            foreach ($firm->members()->orderBy('name')->get() as $m) {
                $count = AccountAssignment::query()->where('firm_member_id', $m->getKey())->count();
                $this->line("   · {$m->name} <{$m->email}> [{$m->role->value}] — {$count} account(s)");
            }
        }

        return self::SUCCESS;
    }

    private function firm(): Firm
    {
        return Firm::query()->findOrFail((int) $this->req('firm'));
    }

    private function req(string $option): string
    {
        $value = trim((string) $this->option($option));
        if ($value === '') {
            $this->error("--{$option} is required for this action.");
            exit(self::INVALID);
        }

        return $value;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
