<?php

use App\Models\Article;
use App\Support\ActorLabel;
use Livewire\Component;

return new class extends Component
{
    public ?Article $article = null;

    public function mount(int $id): void
    {
        // گیت روت به‌تنهایی کافی نیست (#1632): خواندن صفحه هم اینجا
        // authorize می‌شود تا برگه‌ی بازمانده بعد از لغو مجوز، داده ندهد.
        $this->authorize('manage_articles');

        // `int $id` + `findOrFail` — پارامتر تایپ‌شده‌ی مدل با route key
        // بایند می‌شود و برای مدلی با route key غیر id، قبل از هر چک
        // authorize، 404 می‌دهد (#936).
        $this->article = Article::query()
            ->with(['user' => fn ($q) => ActorLabel::eagerLoadActor($q)])
            ->findOrFail($id);
    }

    public function authorLabel(): string
    {
        $article = $this->article;

        if ($article === null) {
            return 'سیستم';
        }

        return ActorLabel::for($article->user) ?: ($article->user?->name ?? 'سیستم');
    }
}; ?>

<div>
    <x-header title="مشاهده مقاله" separator progress-indicator>
        <x-slot:actions>
            <x-theme-selector/>
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        @if($article)
            @if($article->coverImageUrl())
                <img src="{{ $article->coverImageUrl() }}" alt="{{ $article->title }}" class="w-full max-h-80 object-cover rounded-lg mb-4" />
            @endif

            <div class="flex flex-wrap gap-2 items-center mb-2">
                <span class="badge badge-outline">{{ $article->categoryLabel() }}</span>
                @if($article->isPublished())
                    <span class="badge badge-success">منتشرشده</span>
                @else
                    <span class="badge badge-warning">پیش‌نویس</span>
                @endif
                <span class="text-sm text-base-content/70">نویسنده: {{ $this->authorLabel() }}</span>
                <span class="text-sm text-base-content/70">{{ $article->published_at?->format('Y/m/d') ?? $article->created_at?->format('Y/m/d') }}</span>
            </div>

            <h1 class="text-2xl font-bold mb-2">{{ $article->title }}</h1>

            @if($article->excerpt)
                <p class="text-base-content/70 mb-4">{{ $article->excerpt }}</p>
            @endif

            <div class="whitespace-pre-line leading-relaxed">{{ $article->body }}</div>

            <div class="mt-6">
                <x-button link="/articles" label="بازگشت به مقالات" icon="o-arrow-right" class="btn-ghost" />
            </div>
        @endif
    </x-card>
</div>
