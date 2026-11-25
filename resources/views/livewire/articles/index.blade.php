<?php

use App\Models\Article;
use App\Support\ActorLabel;
use App\Traits\PersianNormalizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

return new class extends Component
{
    use PersianNormalizer;
    use Toast;
    use WithFileUploads;
    use WithPagination;

    public string $title = '';

    public string $category = 'news';

    public ?string $excerpt = null;

    public ?TemporaryUploadedFile $coverImage = null;

    // مسیر ذخیره‌شده‌ی تصویر شاخص در حال ویرایش؛ `null` یعنی مقاله
    // تصویری ندارد. آپلود تازه جای آن را می‌گیرد.
    public ?string $coverImagePath = null;

    // URL نمایشی تصویر فعلی در فرم ویرایش؛ با آپلود تازه یا حذف،
    // خالی می‌شود.
    public ?string $coverImageUrl = null;

    public string $body = '';

    public string $status = 'draft';

    #[Locked]
    public ?int $editingId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $categoryFilter = 'all';

    public int $perPage = 20;

    public bool $showForm = false;

    public function mount(): void
    {
        // گیت روت به‌تنهایی کافی نیست (#1632): خواندن صفحه هم اینجا
        // authorize می‌شود تا برگه‌ی بازمانده بعد از لغو مجوز، داده ندهد.
        $this->authorize('manage_articles');
    }

    // تغییر جستجو یا فیلترها صفحه را به اول برمی‌گرداند؛ وگرنه صفحه‌ی
    // ۲ به‌بعدِ لیستِ فیلترشده خالی می‌ماند.
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset(['title', 'category', 'excerpt', 'body', 'status', 'editingId', 'showForm', 'coverImage', 'coverImagePath', 'coverImageUrl']);
        $this->category = 'news';
        $this->status = 'draft';
    }

    public function startCreate(): void
    {
        $this->authorize('manage_articles');
        $this->resetValidation();
        $this->reset(['title', 'category', 'excerpt', 'body', 'status', 'editingId', 'coverImage', 'coverImagePath', 'coverImageUrl']);
        $this->category = 'news';
        $this->status = 'draft';
        $this->showForm = true;
    }

    public function createArticle(): void
    {
        $this->authorize('manage_articles');
        $data = $this->validateForm();

        Article::create([
            'title' => $data['title'],
            'category' => $data['category'],
            'excerpt' => $data['excerpt'],
            'cover_image' => $this->storeCoverImage(),
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published' ? now() : null,
            'user_id' => Auth::id(),
        ]);

        $this->success("«{$data['title']}» ایجاد شد", 'با موفقیت', position: 'toast-bottom');
        $this->cancelEdit();
    }

    public function editArticle(int $id): void
    {
        $this->authorize('manage_articles');

        $article = Article::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $id;
        $this->title = $article->title;
        $this->category = $article->category;
        $this->excerpt = $article->excerpt;
        $this->coverImage = null;
        $this->coverImagePath = $article->cover_image;
        $this->coverImageUrl = $article->coverImageUrl();
        $this->body = $article->body;
        $this->status = $article->status;
        // ویرایش کامل در همان فرم ساخت انجام می‌شود (عنوان، دسته،
        // خلاصه، متن و وضعیت)؛ ویرایش درون‌خطی جدول فقط عنوان را
        // می‌زد، پس فرمِ ویرایش اینجا باز می‌شود.
        $this->showForm = true;
    }

    public function updateArticle(): void
    {
        $this->authorize('manage_articles');
        $data = $this->validateForm();

        $article = Article::findOrFail($this->editingId);

        $coverPath = $article->cover_image;

        if ($this->coverImage !== null) {
            // آپلود تازه جای تصویر قبلی را می‌گیرد؛ فایل قبلی بعد از
            // ذخیره‌ی موفق فایل تازه پاک می‌شود.
            $coverPath = $this->storeCoverImage();
            Article::deleteUploadedCover($article->cover_image);
        } elseif ($this->coverImagePath === null) {
            // تصویر با دکمه‌ی «حذف تصویر» برداشته شده است.
            Article::deleteUploadedCover($article->cover_image);
            $coverPath = null;
        }

        $article->update([
            'title' => $data['title'],
            'category' => $data['category'],
            'excerpt' => $data['excerpt'],
            'cover_image' => $coverPath,
            'body' => $data['body'],
            'status' => $data['status'],
            // انتشارِ تازه زمان انتشار می‌خورد؛ برگشت به پیش‌نویس آن را
            // خالی می‌کند؛ انتشارِ از پیش منتشرشده زمان قبلی را نگه می‌دارد.
            'published_at' => $data['status'] === 'published'
                ? ($article->published_at ?? now())
                : null,
        ]);

        $this->success("«{$data['title']}» بروزرسانی شد", 'با موفقیت', position: 'toast-bottom');
        $this->cancelEdit();
    }

    // فقط وضعیت فرم را پاک می‌کند؛ فایل روی دیسک هنگام ذخیره‌ی ویرایش
    // پاک می‌شود و با «لغو» هیچ اتفاقی نمی‌افتد.
    public function removeCoverImage(): void
    {
        $this->authorize('manage_articles');
        $this->coverImage = null;
        $this->coverImagePath = null;
        $this->coverImageUrl = null;
    }

    public function togglePublish(int $id): void
    {
        $this->authorize('manage_articles');

        $article = Article::findOrFail($id);

        if ($article->isPublished()) {
            $article->update(['status' => 'draft', 'published_at' => null]);
        } else {
            $article->update(['status' => 'published', 'published_at' => $article->published_at ?? now()]);
        }
    }

    public function delete(int $id): void
    {
        $this->authorize('manage_articles');

        $article = Article::findOrFail($id);

        try {
            $article->delete();
            $this->warning("«{$article->title}» حذف شد", 'با موفقیت', position: 'toast-bottom');
        } catch (\Exception $e) {
            $this->error('امکان حذف وجود ندارد.', position: 'toast-bottom');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(): array
    {
        return $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', array_keys(Article::CATEGORIES)),
            'excerpt' => 'nullable|string|max:500',
            // SVG عمداً مجاز نیست: SVG آپلودشده می‌تواند اسکریپت داشته
            // باشد و نمایش درون‌مرورگری‌اش stored XSS می‌شود.
            'coverImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'body' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);
    }

    private function storeCoverImage(): ?string
    {
        if ($this->coverImage === null) {
            return null;
        }

        $path = $this->coverImage->store('article-covers', 'local');

        return $path === false ? null : $path;
    }

    /**
     * گزینه‌های فیلد «دسته» — کلیدها با option-value/option-label هم‌خوان‌اند.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function categoryOptions(): array
    {
        $options = [];

        foreach (Article::CATEGORIES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        $options = [];

        foreach (Article::STATUSES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1 hidden sm:table-cell'],
            ['key' => 'cover_image', 'label' => 'تصویر', 'sortable' => false],
            ['key' => 'title', 'label' => 'عنوان', 'class' => 'flex-1'],
            ['key' => 'category', 'label' => 'دسته'],
            ['key' => 'status', 'label' => 'وضعیت'],
            ['key' => 'user', 'label' => 'نویسنده', 'sortable' => false],
            ['key' => 'published_at', 'label' => 'تاریخ انتشار'],
        ];
    }

    public function articles(): LengthAwarePaginator
    {
        // مقالات سراسری‌اند و unit-scoped نیستند: هیچ whereIn واحدی اینجا
        // نیست و نباید هم باشد — مقاله به واحد خاصی تعلق ندارد.
        $query = Article::query()->with(['user' => fn ($q) => ActorLabel::eagerLoadActor($q)]);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        if (! empty($this->search)) {
            // جستجوی ساده روی عنوان، هم‌راستا با maintenance.index — بدون
            // ستون folded: برای یک جستجوی عنوانِ ساده، ستون generated و
            // ایندکس trgm اضافه نمی‌شود.
            // Issue #1722 (رفع‌شده در #1775 برای جستجوی سراسری): «%» و «_»
            // در ورودی کاربر متاکاراکتر LIKE‌ اند، نه متن عادی — فقط escape
            // می‌شوند؛ نرمالایز نمی‌شوند چون ستون folded ندارد.
            $query->where('title', 'LIKE', '%'.self::escapeLikeWildcards($this->search).'%');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'articles' => $this->articles(),
            'headers' => $this->headers(),
        ];
    }

    public function authorLabel(Article $article): string
    {
        return ActorLabel::for($article->user) ?: ($article->user?->name ?? 'سیستم');
    }
}; ?>

<div>
    <x-header title="مقالات" separator progress-indicator>
        <x-slot:actions>
            <x-theme-selector/>
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <div class="flex gap-2 items-center mb-4">
            <x-ui.icon-button name="مقاله جدید" label="مقاله جدید" class="btn-success" wire:click="startCreate" responsive icon="o-plus"/>
            <div class="flex-1">
                <x-input
                    placeholder="جستجو در عنوان..."
                    wire:model.live.debounce="search"
                    clearable
                    icon="o-magnifying-glass"
                    class="w-full"
                />
            </div>
            <div class="w-40">
                <x-select
                    wire:model.live="statusFilter"
                    :options="[['value' => 'all', 'label' => 'همه وضعیت‌ها'], ['value' => 'draft', 'label' => 'پیش‌نویس'], ['value' => 'published', 'label' => 'منتشرشده']]"
                    option-value="value"
                    option-label="label"
                />
            </div>
            <div class="w-40">
                <x-select
                    wire:model.live="categoryFilter"
                    :options="array_merge([['value' => 'all', 'label' => 'همه دسته‌ها']], $this->categoryOptions())"
                    option-value="value"
                    option-label="label"
                />
            </div>
        </div>

        @if($showForm)
            <div class="flex flex-col gap-3 mb-4 p-3 bg-base-200 rounded-lg">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <x-input wire:model="title" label="عنوان" placeholder="عنوان مقاله" required />
                        @error('title') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="w-full sm:w-40">
                        <x-select
                            wire:model="category"
                            label="دسته"
                            :options="$this->categoryOptions()"
                            option-value="value"
                            option-label="label"
                        />
                    </div>
                    <div class="w-full sm:w-40">
                        <x-select
                            wire:model="status"
                            label="وضعیت"
                            :options="$this->statusOptions()"
                            option-value="value"
                            option-label="label"
                        />
                    </div>
                </div>
                <div>
                    <x-input wire:model="excerpt" label="خلاصه" placeholder="خلاصه کوتاه مقاله (اختیاری)" />
                    @error('excerpt') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-file wire:model="coverImage" label="تصویر شاخص" accept="image/*" />
                    @error('coverImage') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    @if($coverImage)
                        <img src="{{ $coverImage->temporaryUrl() }}" alt="پیش‌نمایش تصویر شاخص" class="mt-2 w-32 h-20 object-cover rounded-lg" />
                    @elseif($coverImageUrl)
                        <div class="flex items-center gap-2 mt-2">
                            <img src="{{ $coverImageUrl }}" alt="تصویر شاخص فعلی" class="w-32 h-20 object-cover rounded-lg" />
                            <x-button wire:click="removeCoverImage" label="حذف تصویر" icon="o-trash" class="btn-ghost btn-sm text-error" />
                        </div>
                    @endif
                </div>
                <div>
                    <label class="label"><span class="label-text">متن مقاله</span></label>
                    <textarea wire:model="body" rows="8" class="textarea textarea-bordered w-full" placeholder="متن کامل مقاله..."></textarea>
                    @error('body') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2">
                    @if($editingId)
                        <x-button wire:click="updateArticle" label="ذخیره تغییرات" icon="o-check" class="btn-primary" spinner />
                    @else
                        <x-button wire:click="createArticle" label="ذخیره" icon="o-check" class="btn-primary" spinner />
                    @endif
                    <x-button wire:click="cancelEdit" label="لغو" icon="o-x-mark" class="btn-ghost" />
                </div>
            </div>
        @endif

        <x-table
            :headers="$headers"
            :rows="$articles"
            with-pagination
            per-page="perPage"
            :per-page-values="[10, 20, 50]">

            @scope('cell_cover_image', $article)
                @if($article->coverImageUrl())
                    <img src="{{ $article->coverImageUrl() }}" alt="" class="w-12 h-12 object-cover rounded" />
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_category', $article)
                {{ $article->categoryLabel() }}
            @endscope

            @scope('cell_status', $article)
                @if($article->isPublished())
                    <span class="badge badge-success badge-sm">منتشرشده</span>
                @else
                    <span class="badge badge-warning badge-sm">پیش‌نویس</span>
                @endif
            @endscope

            @scope('cell_user', $article)
                {{ $this->authorLabel($article) }}
            @endscope

            @scope('cell_published_at', $article)
                {{ $article->published_at?->format('Y/m/d') ?? '—' }}
            @endscope

            @scope('cell_title', $article)
                <a href="{{ route('articles.show', $article->id) }}" wire:navigate class="link link-primary">{{ $article->title }}</a>
            @endscope

            @scope('actions', $article)
                <div class="flex gap-1">
                    @if($this->editingId !== $article->id)
                        <x-ui.icon-button name="تغییر وضعیت انتشار"
                            icon="o-eye"
                            wire:click="togglePublish({{ $article->id }})"
                            class="btn-ghost btn-sm text-primary"
                        />
                        <x-ui.icon-button name="ویرایش مقاله"
                            icon="o-pencil"
                            wire:click="editArticle({{ $article->id }})"
                            class="btn-ghost btn-sm text-primary"
                        />
                        <x-ui.icon-button name="حذف مقاله"
                            icon="o-trash"
                            wire:click="delete({{ $article->id }})"
                            wire:confirm="آیا مطمئن هستید؟"
                            spinner
                            class="btn-ghost btn-sm text-error"
                        />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
