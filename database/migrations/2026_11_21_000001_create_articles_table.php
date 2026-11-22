<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بخش مقالات (بلاگ داخلی داشبورد).
     *
     * مقالات سراسری‌اند و unit-scoped نیستند: مقاله به هیچ واحدی تعلق
     * ندارد و نویسنده‌اش کاربرِ سازنده است (`user_id`). `slug` کلید
     * پایدارِ مقاله است و یک‌بار در ساخت ساخته می‌شود؛ بعد از آن عوض
     * نمی‌شود. `published_at` فقط وقتی `status = 'published'` است پر
     * می‌شود و با برگشت به پیش‌نویس خالی می‌شود.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('news');
            $table->string('excerpt', 500)->nullable();
            $table->text('body');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
