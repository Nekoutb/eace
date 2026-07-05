<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Monitoring\ServiceCategory;
use Illuminate\Support\Carbon;

/**
 * One subscription / infrastructure service the operator monitors (hosting, domain,
 * storage, email, API, SSL …) with its renewal date, amount, auto-pay flag and an
 * optional health URL. Central (landlord) data — never tenant-scoped.
 *
 * @property int $id
 * @property string $name
 * @property ?string $provider
 * @property ServiceCategory $category
 * @property ?string $powers
 * @property ?string $amount
 * @property string $currency
 * @property string $billing_cycle
 * @property ?Carbon $renews_at
 * @property bool $auto_pay
 * @property ?string $health_url
 * @property ?string $last_status
 * @property ?int $last_http_code
 * @property ?Carbon $last_checked_at
 * @property bool $is_active
 * @property ?string $notes
 */
class MonitoredService extends CentralModel
{
    protected $table = 'monitored_services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'amount' => 'decimal:2',
            'renews_at' => 'date',
            'auto_pay' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /** Days within which a renewal counts as "due soon". */
    public static function dueSoonDays(): int
    {
        return (int) config('niu.monitoring.due_soon_days', 14);
    }

    /** Whole days until renewal: positive in the future, 0 today, negative once overdue. */
    public function daysToRenewal(): ?int
    {
        if ($this->renews_at === null) {
            return null;
        }

        return (int) Carbon::now()->startOfDay()->diffInDays($this->renews_at->startOfDay(), false);
    }

    /** none | ok | due_soon | overdue */
    public function renewalStatus(): string
    {
        $days = $this->daysToRenewal();

        if ($days === null) {
            return 'none';
        }
        if ($days < 0) {
            return 'overdue';
        }
        if ($days <= self::dueSoonDays()) {
            return 'due_soon';
        }

        return 'ok';
    }
}
