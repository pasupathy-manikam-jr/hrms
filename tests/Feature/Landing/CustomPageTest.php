<?php

namespace Tests\Feature\Landing;

use App\Models\CustomPage;
use Database\Seeders\Modules\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_pages_are_seeded_and_listed(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->seed(LandingContentSeeder::class);

        $this->assertDatabaseCount('custom_pages', 6);

        $this->actingAs($this->userWithRole())
            ->get(route('landing-page.custom-pages.index', ['search' => 'privacy']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('landing-page/custom-pages/index')
                ->has('pages.data', 1)
                ->where('pages.data.0.slug', 'privacy-policy'));
    }

    public function test_pages_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->userWithRole());

        $this->get(route('landing-page.custom-pages.create'))->assertInertia(fn ($page) => $page->component('landing-page/custom-pages/form')->where('page', null));

        $this->post(route('landing-page.custom-pages.store'), ['title' => '', 'content' => ''])
            ->assertSessionHasErrors(['title', 'content']);

        // A blank slug is generated from the title.
        $this->post(route('landing-page.custom-pages.store'), ['title' => 'Cookie Policy!', 'content' => '<p>Hi</p>', 'is_active' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('landing-page.custom-pages.index'));

        $page = CustomPage::where('slug', 'cookie-policy')->firstOrFail();
        $this->assertTrue($page->is_active);
        $this->assertSame(0, $page->sort_order);

        // Slugs are unique.
        $this->post(route('landing-page.custom-pages.store'), ['title' => 'Cookie Policy', 'content' => 'x'])
            ->assertSessionHasErrors('slug');

        $this->get(route('landing-page.custom-pages.edit', $page))->assertInertia(fn ($p) => $p->where('page.slug', 'cookie-policy'));

        $this->put(route('landing-page.custom-pages.update', $page), [
            'title' => 'Cookies', 'slug' => 'cookie-policy', 'content' => 'Updated', 'meta_title' => 'Cookies - HRM', 'is_active' => false, 'sort_order' => 9,
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(['Cookies', 'cookie-policy', 'Cookies - HRM', false, 9], [$page->title, $page->slug, $page->meta_title, $page->is_active, $page->sort_order]);

        $this->delete(route('landing-page.custom-pages.destroy', $page));
        $this->assertModelMissing($page);
    }

    public function test_active_page_is_public_and_sanitized(): void
    {
        CustomPage::create(['title' => 'Terms', 'slug' => 'terms', 'content' => '<b>Bold</b><script>alert(1)</script><a href="javascript:x()" onclick="y">l</a>', 'meta_title' => 'Terms - HRM']);
        CustomPage::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'x', 'is_active' => false]);

        $this->get(route('custom-page.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('custom-page')
                ->where('page.title', 'Terms')
                ->where('page.meta_title', 'Terms - HRM')
                ->where('page.content', '<b>Bold</b><a>l</a>')
                ->has('footer.description')
                ->has('customPages', 1));

        $this->get(route('custom-page.show', 'draft'))->assertNotFound();
        $this->get(route('custom-page.show', 'missing'))->assertNotFound();

        // Only active pages are linked from the landing page.
        $this->get(route('home'))->assertInertia(fn ($page) => $page->where('customPages', [['title' => 'Terms', 'slug' => 'terms']]));
    }

    public function test_employees_cannot_manage_custom_pages(): void
    {
        $page = CustomPage::create(['title' => 'Terms', 'slug' => 'terms', 'content' => 'x']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('landing-page.custom-pages.index'))->assertForbidden();
        $this->post(route('landing-page.custom-pages.store'), ['title' => 'X', 'content' => 'x'])->assertForbidden();
        $this->delete(route('landing-page.custom-pages.destroy', $page))->assertForbidden();
        $this->assertModelExists($page);
    }
}
