<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تصویر شاخص مقاله.
     *
     * دو جور مقدار می‌گیرد و `Article::coverImageUrl()` فرقشان را می‌داند:
     * - مسیر فایل استاتیک زیر `public/` مثل `images/articles/news.svg`
     *   (تصویرهای نمونه‌ی سیدر) که با `asset()` سرو می‌شود؛
     * - مسیر روی دیسک خصوصی `local` مثل `article-covers/abc.jpg`
     *   (آپلود کاربر) که فقط از روت احرازشده‌ی `articles.cover` سرو
     *   می‌شود (هم‌راستا با پیوست‌های تیکت، #1276).
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('cover_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
