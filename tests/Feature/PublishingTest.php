<?php

namespace Tests\Feature;

use App\Jobs\GenerateWebsiteJob;
use App\Models\User;
use App\Models\Website;
use App\Services\Sources\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    private function generated(?User $user = null, string $name = 'Glow Salon'): array
    {
        $user ??= User::factory()->create();
        $business = $this->businessFor($user, ['name' => $name]);
        $this->fact($business, 'phone', '9820012345');
        $website = $this->websiteFor($business);
        GenerateWebsiteJob::dispatchSync($website->id, ['use_ai' => false]);

        return [$user, $business, $website->fresh('sections')];
    }

    public function test_publish_creates_public_page_and_snapshot(): void
    {
        [$user, , $website] = $this->generated();
        $this->assertSame('glow-salon-mumbai', $website->slug);

        $this->get('/sites/glow-salon-mumbai')->assertNotFound();

        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk()
            ->assertJsonPath('url', url('/sites/glow-salon-mumbai'));
        $website->refresh();
        $this->assertSame(Website::STATUS_PUBLISHED, $website->status);
        $this->assertNotNull($website->published_at);

        auth()->logout();
        $this->get('/sites/glow-salon-mumbai')->assertOk()
            ->assertSee('<title inertia>Glow Salon — Mumbai</title>', false)
            ->assertInertia(fn ($p) => $p->component('PublicSite')->where('site.business.name', 'Glow Salon')->has('site.sections'));
    }

    public function test_edits_go_live_only_when_republished_and_hidden_sections_stay_hidden(): void
    {
        [$user, , $website] = $this->generated();
        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk();
        $hero = $website->sections->firstWhere('type', 'hero');
        $about = $website->sections->firstWhere('type', 'about');

        $this->actingAs($user)->putJson("/websites/{$website->id}/sections/{$hero->id}", ['content' => [...$hero->content, 'headline' => 'Brand new headline']])->assertOk();
        $this->actingAs($user)->putJson("/websites/{$website->id}/sections/{$about->id}", ['is_visible' => false])->assertOk();
        $this->assertTrue($website->fresh()->hasUnpublishedChanges());

        $this->get('/sites/glow-salon-mumbai')->assertInertia(fn ($p) => $p->where('site.sections.0.content.headline', 'Glow Salon'));

        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk();
        $this->assertFalse($website->fresh()->hasUnpublishedChanges());
        $this->get('/sites/glow-salon-mumbai')->assertInertia(fn ($p) => $p
            ->where('site.sections.0.content.headline', 'Brand new headline')
            ->where('site.sections', fn ($sections) => collect($sections)->doesntContain('type', 'about')));
    }

    public function test_unpublish_removes_public_access_but_keeps_website(): void
    {
        [$user, , $website] = $this->generated();
        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk();
        $this->actingAs($user)->postJson("/websites/{$website->id}/unpublish")->assertOk();

        $this->get('/sites/glow-salon-mumbai')->assertNotFound()->assertInertia(fn ($p) => $p->component('Error')->where('status', 404));
        $this->assertDatabaseHas('websites', ['id' => $website->id, 'status' => 'unpublished']);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('stats.total', 1)->where('stats.published', 0));
    }

    public function test_slugs_are_unique_validated_and_checkable(): void
    {
        $user = User::factory()->create();
        [, , $first] = $this->generated($user);
        [, , $second] = $this->generated($user);
        $this->assertSame('glow-salon-mumbai-2', $second->slug, 'Slug conflicts get a suffix');

        $this->actingAs($user)->postJson("/websites/{$second->id}/publish", ['slug' => 'glow-salon-mumbai'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->actingAs($user)->postJson("/websites/{$second->id}/publish", ['slug' => 'Bad Slug!'])->assertStatus(422);
        $this->actingAs($user)->postJson("/websites/{$second->id}/publish", ['slug' => 'admin'])->assertStatus(422);

        $this->actingAs($user)->getJson("/websites/{$second->id}/slug-check?slug=glow-salon-mumbai")->assertJson(['valid' => true, 'available' => false]);
        $this->actingAs($user)->getJson("/websites/{$second->id}/slug-check?slug=glow-bandra")->assertJson(['valid' => true, 'available' => true]);

        $this->actingAs($user)->postJson("/websites/{$second->id}/publish", ['slug' => 'glow-bandra'])->assertOk();
        $this->get('/sites/glow-bandra')->assertOk();
    }

    public function test_publishing_requires_visible_content(): void
    {
        $user = User::factory()->create();
        $website = $this->websiteFor($this->businessFor($user));
        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertStatus(422)->assertJsonValidationErrors('website');
    }

    public function test_images_are_public_only_when_used_on_a_published_site(): void
    {
        Storage::fake('local');
        [$user, $business, $website] = $this->generated();
        $service = app(MediaService::class);
        $used = $service->storeImage($business, UploadedFile::fake()->image('used.jpg'));
        $unused = $service->storeImage($business, UploadedFile::fake()->image('unused.jpg'));

        $hero = $website->sections->firstWhere('type', 'hero');
        $this->actingAs($user)->putJson("/websites/{$website->id}/sections/{$hero->id}", ['content' => [...$hero->content, 'image_id' => $used->id]])->assertOk();
        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk();
        auth()->logout();

        $this->get("/media/{$used->uuid}")->assertOk();
        $this->get("/media/{$unused->uuid}")->assertNotFound();
    }

    public function test_deleting_a_business_takes_its_site_offline(): void
    {
        [$user, $business, $website] = $this->generated();
        $this->actingAs($user)->postJson("/websites/{$website->id}/publish")->assertOk();
        $this->actingAs($user)->delete("/businesses/{$business->id}")->assertRedirect();
        $this->get('/sites/glow-salon-mumbai')->assertNotFound();
    }
}
