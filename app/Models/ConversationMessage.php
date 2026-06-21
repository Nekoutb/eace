<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Support\MessageAuthor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central (landlord) model. One message in a customer-service conversation thread —
 * from the customer, a support agent, or the AI bot.
 *
 * @property int $conversation_id
 * @property MessageAuthor $author
 * @property ?string $author_name
 * @property ?int $author_id
 * @property string $body
 * @property ?Carbon $created_at
 */
class ConversationMessage extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['author' => MessageAuthor::class];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
