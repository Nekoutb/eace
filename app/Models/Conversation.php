<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Central (landlord) model. One customer-service conversation (a ticket thread)
 * raised by a workspace, triaged and answered by support staff. Lives centrally so
 * every workspace's requests sit in one inbox. The timeline stamps
 * (first_response_at, escalated_at, resolved_at, sla_due_at) are the source of the
 * customer-experience KPIs.
 *
 * @property ?string $tenant_id
 * @property ?string $tenant_name
 * @property ?string $requester_name
 * @property ?string $requester_email
 * @property ?string $subject
 * @property ConversationNature $nature
 * @property ConversationStatus $status
 * @property string $priority
 * @property string $channel
 * @property ?int $assigned_to
 * @property bool $bot_resolved
 * @property ?int $csat_score
 * @property ?Carbon $first_response_at
 * @property ?Carbon $escalated_at
 * @property ?Carbon $resolved_at
 * @property ?Carbon $last_activity_at
 * @property ?Carbon $sla_due_at
 * @property ?Carbon $created_at
 */
class Conversation extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nature' => ConversationNature::class,
            'status' => ConversationStatus::class,
            'bot_resolved' => 'boolean',
            'csat_score' => 'integer',
            'first_response_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'sla_due_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Append a message and advance the lifecycle: the first support-side reply
     * stamps the response time; a customer reply re-opens an awaiting thread.
     */
    public function postMessage(MessageAuthor $author, string $body, ?string $name = null, ?int $authorId = null): ConversationMessage
    {
        $message = $this->messages()->create([
            'author' => $author,
            'author_name' => $name,
            'author_id' => $authorId,
            'body' => $body,
        ]);

        $this->last_activity_at = now();

        if ($author->isStaffSide() && $this->first_response_at === null) {
            $this->first_response_at = now();
        }

        if ($author === MessageAuthor::Customer && $this->status === ConversationStatus::AwaitingCustomer) {
            $this->status = ConversationStatus::Open;
        }

        $this->save();

        return $message;
    }

    public function resolve(): void
    {
        $this->forceFill([
            'status' => ConversationStatus::Resolved,
            'resolved_at' => $this->resolved_at ?? now(),
            'last_activity_at' => now(),
        ])->save();
    }

    public function reopen(): void
    {
        $this->forceFill([
            'status' => ConversationStatus::Open,
            'resolved_at' => null,
            'last_activity_at' => now(),
        ])->save();
    }

    public function escalate(): void
    {
        $this->forceFill([
            'status' => ConversationStatus::Escalated,
            'escalated_at' => $this->escalated_at ?? now(),
            'last_activity_at' => now(),
        ])->save();
    }

    /** Minutes from open to first support reply, or null if not yet answered. */
    public function firstResponseMinutes(): ?int
    {
        return $this->first_response_at === null || $this->created_at === null
            ? null
            : (int) round($this->created_at->diffInMinutes($this->first_response_at, true));
    }

    /** Minutes from open to resolution, or null if unresolved. */
    public function resolutionMinutes(): ?int
    {
        return $this->resolved_at === null || $this->created_at === null
            ? null
            : (int) round($this->created_at->diffInMinutes($this->resolved_at, true));
    }

    public function breachedSla(): bool
    {
        return $this->sla_due_at !== null
            && $this->first_response_at === null
            && now()->greaterThan($this->sla_due_at);
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ConversationStatus::openValues());
    }
}
