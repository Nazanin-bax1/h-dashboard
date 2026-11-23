<?php

use App\Models\Article;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Concerns\InteractsWithTestSetup;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithTestSetup::class);

// تصویر JPEG یک‌در‌یک واقعی — `UploadedFile::fake()->image()` به GD نیاز
// دارد و GD در CI نصب نیست، پس بایت‌های JPEG را مستقیم می‌سازیم.
function fakeArticleCoverImage(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'cover.jpg',
        base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AvwH/2Q=='),
        'image/jpeg',
    );
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->seedLookupTables();

    $this->unit = Unit::create(['name' => 'واحد تست']);

    $this->user = User::factory()->create([
        'n_code' => (string) fake()->unique()->numerify('##########'),
        'password' => Hash::make('password'),
    ]);
    $this->user->units()->attach($this->unit->id, ['role' => 'staff', 'is_primary' => true]);
    $this->user->givePermissionTo('manage_articles');
});

test('guest is redirected from articles page', function () {
    $this->get('/articles')->assertRedirect('/login');
});

test('authenticated user without manage_articles permission is denied', function () {
    $user = User::factory()->create([
        'n_code' => (string) fake()->unique()->numerify('##########'),
        'password' => Hash::make('password'),
    ]);
    $unit = Unit::create(['name' => 'واحد دیگر']);
    $user->units()->attach($unit->id, ['role' => 'staff', 'is_primary' => true]);

    $this->actingAs($user);
    $this->get('/articles')->assertStatus(403);
    $this->get('/articles/1')->assertStatus(403);
});

test('authenticated user with permission can load articles page', function () {
    $this->actingAs($this->user);
    Livewire::test('articles.index')
        ->assertStatus(200);
});

test('create draft article via livewire', function () {
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'آموزش شبکه')
        ->set('category', 'tutorial')
        ->set('excerpt', 'خلاصه کوتاه')
        ->set('body', 'متن کامل مقاله')
        ->set('status', 'draft')
        ->call('createArticle')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articles', [
        'title' => 'آموزش شبکه',
        'slug' => 'آموزش-شبکه',
        'category' => 'tutorial',
        'status' => 'draft',
        'published_at' => null,
        'user_id' => $this->user->id,
    ]);
});

test('create published article sets published_at', function () {
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'خبر مهم')
        ->set('category', 'news')
        ->set('body', 'متن خبر')
        ->set('status', 'published')
        ->call('createArticle')
        ->assertHasNoErrors();

    $article = Article::query()->where('title', 'خبر مهم')->firstOrFail();

    expect($article->status)->toBe('published')
        ->and($article->published_at)->not->toBeNull();
});

test('create article with a cover image', function () {
    Storage::fake('local');
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'مقاله با تصویر')
        ->set('category', 'news')
        ->set('body', 'متن مقاله با تصویر')
        ->set('status', 'published')
        ->set('coverImage', fakeArticleCoverImage())
        ->call('createArticle')
        ->assertHasNoErrors();

    $article = Article::query()->where('title', 'مقاله با تصویر')->firstOrFail();

    expect($article->cover_image)->toStartWith('article-covers/');
    Storage::disk('local')->assertExists($article->cover_image);
});

test('cover route serves the uploaded image', function () {
    Storage::fake('local');
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'مقاله با تصویر')
        ->set('category', 'news')
        ->set('body', 'متن مقاله')
        ->set('status', 'draft')
        ->set('coverImage', fakeArticleCoverImage())
        ->call('createArticle')
        ->assertHasNoErrors();

    $article = Article::query()->where('title', 'مقاله با تصویر')->firstOrFail();

    $this->get(route('articles.cover', $article))->assertOk();
});

test('deleting an article removes its uploaded cover file', function () {
    Storage::fake('local');
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'مقاله حذف‌شدنی با تصویر')
        ->set('category', 'news')
        ->set('body', 'متن مقاله')
        ->set('status', 'draft')
        ->set('coverImage', fakeArticleCoverImage())
        ->call('createArticle')
        ->assertHasNoErrors();

    $article = Article::query()->where('title', 'مقاله حذف‌شدنی با تصویر')->firstOrFail();
    Storage::disk('local')->assertExists($article->cover_image);

    Livewire::test('articles.index')
        ->call('delete', $article->id);

    Storage::disk('local')->assertMissing($article->cover_image);
});

test('duplicate titles get unique slugs', function () {
    $this->actingAs($this->user);

    Article::factory()->create(['title' => 'آموزش شبکه', 'slug' => 'آموزش-شبکه', 'user_id' => $this->user->id]);

    Livewire::test('articles.index')
        ->set('title', 'آموزش شبکه')
        ->set('category', 'tutorial')
        ->set('body', 'متن دوم')
        ->set('status', 'draft')
        ->call('createArticle')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articles', [
        'title' => 'آموزش شبکه',
        'slug' => 'آموزش-شبکه-2',
    ]);
});

test('blank excerpt is generated from the body', function () {
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', 'مقاله بدون خلاصه')
        ->set('category', 'news')
        ->set('body', 'متن کامل مقاله بدون خلاصه')
        ->set('status', 'draft')
        ->call('createArticle')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articles', [
        'title' => 'مقاله بدون خلاصه',
        'excerpt' => 'متن کامل مقاله بدون خلاصه',
    ]);
});

test('clearing the excerpt on edit regenerates it from the body', function () {
    $this->actingAs($this->user);

    $article = Article::factory()->create([
        'title' => 'مقاله با خلاصه',
        'excerpt' => 'خلاصه دستی',
        'body' => 'متن تازه مقاله',
        'user_id' => $this->user->id,
    ]);

    Livewire::test('articles.index')
        ->call('editArticle', $article->id)
        ->set('excerpt', '')
        ->call('updateArticle')
        ->assertHasNoErrors();

    expect(Article::findOrFail($article->id)->excerpt)->toBe('متن تازه مقاله');
});

test('edit article via livewire', function () {
    $this->actingAs($this->user);

    $article = Article::factory()->create([
        'title' => 'عنوان قدیمی',
        'category' => 'news',
        'body' => 'متن قدیمی',
        'status' => 'draft',
        'user_id' => $this->user->id,
    ]);

    Livewire::test('articles.index')
        ->call('editArticle', $article->id)
        ->set('title', 'عنوان تازه')
        ->set('category', 'guide')
        ->set('body', 'متن تازه')
        ->set('status', 'published')
        ->call('updateArticle')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'title' => 'عنوان تازه',
        'category' => 'guide',
        'body' => 'متن تازه',
        'status' => 'published',
    ]);

    expect(Article::findOrFail($article->id)->published_at)->not->toBeNull();
});

test('toggle publish flips draft and published', function () {
    $this->actingAs($this->user);

    $article = Article::factory()->create([
        'title' => 'مقاله آزمایشی',
        'status' => 'draft',
        'published_at' => null,
        'user_id' => $this->user->id,
    ]);

    Livewire::test('articles.index')
        ->call('togglePublish', $article->id);

    $article->refresh();
    expect($article->status)->toBe('published')
        ->and($article->published_at)->not->toBeNull();

    Livewire::test('articles.index')
        ->call('togglePublish', $article->id);

    $article->refresh();
    expect($article->status)->toBe('draft')
        ->and($article->published_at)->toBeNull();
});

test('delete article via livewire', function () {
    $this->actingAs($this->user);

    $article = Article::factory()->create([
        'title' => 'مقاله حذف‌شدنی',
        'user_id' => $this->user->id,
    ]);

    Livewire::test('articles.index')
        ->call('delete', $article->id);

    $this->assertDatabaseMissing('articles', ['id' => $article->id]);
});

test('search filters the article list by title', function () {
    $this->actingAs($this->user);

    Article::factory()->create(['title' => 'آموزش شبکه', 'user_id' => $this->user->id]);
    Article::factory()->create(['title' => 'خبر سلامت', 'user_id' => $this->user->id]);

    Livewire::test('articles.index')
        ->set('search', 'شبکه')
        ->assertSee('آموزش شبکه')
        ->assertDontSee('خبر سلامت');
});

test('changing the search resets to the first page', function () {
    $this->actingAs($this->user);

    foreach (range(1, 24) as $i) {
        Article::factory()->create(['title' => "مقاله شماره {$i}", 'user_id' => $this->user->id]);
    }
    Article::factory()->create(['title' => 'مقاله خاص جستجو', 'user_id' => $this->user->id]);

    Livewire::test('articles.index')
        ->set('page', 2)
        ->set('search', 'خاص')
        ->assertSee('مقاله خاص جستجو');
});

test('show page renders the article body and author', function () {
    $this->actingAs($this->user);

    $article = Article::factory()->create([
        'title' => 'آموزش شبکه',
        'body' => 'متن کامل مقاله برای نمایش',
        'user_id' => $this->user->id,
    ]);

    $this->get(route('articles.show', $article->id))
        ->assertOk()
        ->assertSee('آموزش شبکه')
        ->assertSee('متن کامل مقاله برای نمایش');
});

test('validation rejects an empty title', function () {
    $this->actingAs($this->user);

    Livewire::test('articles.index')
        ->set('title', '')
        ->set('body', 'متن مقاله')
        ->call('createArticle')
        ->assertHasErrors(['title']);

    $this->assertDatabaseCount('articles', 0);
});

test('article seeder creates sample articles with covers', function () {
    $this->seed(ArticleSeeder::class);

    expect(Article::count())->toBe(8)
        ->and(Article::query()->where('status', 'published')->count())->toBe(6)
        ->and(Article::query()->whereNull('cover_image')->count())->toBe(0);

    // اجرای دوباره مقاله‌ی تکراری نمی‌سازد.
    $this->seed(ArticleSeeder::class);

    expect(Article::count())->toBe(8);
});
