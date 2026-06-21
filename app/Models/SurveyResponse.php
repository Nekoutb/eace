<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Central (landlord) model. One anonymous customer-experience survey response,
 * tagged by workspace but never by individual user. Feeds the satisfaction KPIs.
 *
 * @property ?string $tenant_id
 * @property ?string $tenant_name
 * @property ?int $service_quality
 * @property ?int $ease_of_use
 * @property ?int $responsiveness
 * @property ?int $recommend
 * @property ?string $comment
 * @property ?Carbon $submitted_at
 */
class SurveyResponse extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_quality' => 'integer',
            'ease_of_use' => 'integer',
            'responsiveness' => 'integer',
            'recommend' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /** NPS promoter — would strongly recommend (9–10). */
    public function isPromoter(): bool
    {
        return $this->recommend !== null && $this->recommend >= 9;
    }

    /** NPS detractor — unlikely to recommend (0–6). */
    public function isDetractor(): bool
    {
        return $this->recommend !== null && $this->recommend <= 6;
    }

    /**
     * Net Promoter Score over a set of responses: %promoters − %detractors,
     * rounded to a whole number in the range −100…100. Null when nobody answered
     * the recommend question.
     *
     * @param  Collection<int, SurveyResponse>  $responses
     */
    public static function nps(Collection $responses): ?int
    {
        $scored = $responses->filter(fn (SurveyResponse $r): bool => $r->recommend !== null);
        $n = $scored->count();
        if ($n === 0) {
            return null;
        }

        $promoters = $scored->filter(fn (SurveyResponse $r): bool => $r->isPromoter())->count();
        $detractors = $scored->filter(fn (SurveyResponse $r): bool => $r->isDetractor())->count();

        return (int) round(($promoters - $detractors) / $n * 100);
    }
}
