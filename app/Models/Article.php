<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string|null $excerpt
 * @property string $body
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static Builder<static> where(string $column, mixed $value)
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'news' => 'خبر',
        'tutorial' => 'آموزش',
        'announcement' => 'اطلاعیه',
        'guide' => 'راهنما',
    ];

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'published' => 'منتشرشده',
    ];

    protected $fillable = [
        'title',
        'slug',
        'category',
        'excerpt',
        'body',
        'status',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // slug یک‌بار در ساخت، از روی عنوان ساخته می‌شود و بعد از آن
        // پایدار می‌ماند (مثل slug بیماری‌ها). اگر عنوان فقط نویسه‌های
        // غیرحرفی داشت، به 'article' برمی‌گردد و یکتایی با پسوند عددی
        // تامین می‌شود.
        static::creating(function (Article $article): void {
            if ($article->slug === null || $article->slug === '') {
                $article->slug = static::uniqueSlug(static::makeSlug($article->title));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * slug فارسیِ امن برای URL: حروف و ارقام (از جمله فارسی) می‌مانند و
     * بقیه به `-` تبدیل می‌شوند. `Str::slug` اینجا به کار نمی‌آید چون
     * حروف فارسی را حذف می‌کند و برای عنوان فارسی رشته‌ی خالی می‌دهد.
     */
    public static function makeSlug(string $title): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'article' : $slug;
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
