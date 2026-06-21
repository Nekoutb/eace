<?php

namespace App\Filament\App\Pages;

use App\Support\SupportKnowledge;
use BackedEnum;
use Filament\Pages\Page;

/**
 * A searchable FAQ, rendered from the same bilingual knowledge base the support bot
 * answers from (config/support_kb.php via App\Support\SupportKnowledge). Lets
 * customers self-serve the common questions without opening the chat. Available to
 * every role; sits right after the User Guide in the navigation.
 */
class Faq extends Page
{
    protected string $view = 'filament.app.pages.faq';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.faq');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.faq');
    }

    /**
     * The FAQ grouped by category, in the current language.
     *
     * @return array<string, list<array{q: string, a: string}>>
     */
    public function faqGroups(): array
    {
        return SupportKnowledge::grouped();
    }
}
