<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصویر شاخص آپلودشده‌ی مقاله روی دیسک خصوصی `local` می‌نشیند و فقط
 * از این روت احرازشده سرو می‌شود — هم‌راستا با پیوست‌های تیکت (#1276).
 * تصویرهای استاتیک سیدر (`images/...` زیر `public/`) اینجا نمی‌آیند؛
 * آن‌ها با `asset()` سرو می‌شوند.
 */
class ArticleCoverController extends Controller
{
    use AuthorizesRequests;

    /**
     * پسوندهای امن برای نمایش درون مرورگر. SVG عمداً اینجا نیست:
     * SVG آپلودشده می‌تواند اسکریپت داشته باشد (stored XSS).
     */
    private const INLINE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const DOWNLOAD_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
    ];

    public function show(Article $article): StreamedResponse
    {
        $this->authorize('manage_articles');

        $path = ltrim((string) $article->cover_image, '/');

        // تصویر استاتیک یا خالی اینجا سروی نمی‌شود؛ مقدار دیتابیس هم
        // ورودی کاربر نیست، ولی `..` می‌تواند از ریشه‌ی دیسک بیرون بزند.
        if ($path === '' || str_starts_with($path, 'images/') || in_array('..', explode('/', $path), true)) {
            abort(404);
        }

        $disk = Storage::disk('local');

        // ردیفی که فایلش نیست باید 404 بدهد، نه 500.
        abort_if(! $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::INLINE_EXTENSIONS, true)) {
            abort(404);
        }

        // `response()` نوع MIME را از فایل روی دیسک می‌گیرد، نه از چیزی
        // که آپلودکننده گفته است.
        return $disk->response($path, basename($path), self::DOWNLOAD_HEADERS);
    }
}
