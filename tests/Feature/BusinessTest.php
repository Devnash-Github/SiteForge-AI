<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get('/websites/create')->assertOk()->assertInertia(fn ($p) => $p->component('CreateWebsite'));
    }

    public function test_business_requires_name_and_city(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/businesses', ['name' => '', 'city' => ''])
            ->assertSessionHasErrors(['name', 'city']);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_minimal_business_with_only_name_and_city_works(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/businesses', ['name' => 'Chai Corner', 'city' => 'Pune']);

        $business = Business::firstOrFail();
        $response->assertRedirect("/businesses/{$business->id}/research");
        $this->assertSame($user->id, $business->user_id);
        $this->assertSame(1, $business->websites()->count());
        $this->assertSame('chai-corner-pune', $business->websites()->first()->slug);
        // Research is unavailable without a provider, and that is recorded honestly.
        $this->assertSame('unavailable', $business->fresh()->research_status);
        $this->assertSame('ready', $business->fresh()->status);
        // The owner's own business name is a confirmed fact.
        $this->assertDatabaseHas('business_facts', ['business_id' => $business->id, 'key' => 'business_name', 'status' => BusinessFact::STATUS_CONFIRMED]);
    }

    public function test_links_are_normalised_and_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/businesses', [
            'name' => 'Glow', 'city' => 'Mumbai',
            'instagram_url' => 'facebook.com/glow',
        ])->assertSessionHasErrors('instagram_url');

        $this->actingAs($user)->post('/businesses', [
            'name' => 'Glow', 'city' => 'Mumbai', 'website_url' => 'not a url',
        ])->assertSessionHasErrors('website_url');
    }

    public function test_full_creation_processes_text_pdf_image_and_links(): void
    {
        Storage::fake('local');
        Http::fake([
            '*glowsalon.in*' => Http::response('<html><head><title>Glow Salon Mumbai</title></head><body><h1>Glow Salon</h1><p>Call <a href="tel:+919820012345">+91 98200 12345</a></p></body></html>', 200, ['Content-Type' => 'text/html']),
            '*instagram.com*' => Http::response('', 302, ['Location' => 'https://www.instagram.com/accounts/login/?next=/glow']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/businesses', [
            'name' => 'Glow Salon',
            'city' => 'Mumbai',
            'description' => 'A friendly hair and beauty salon. Email us at hello@glowsalon.in',
            'website_url' => 'glowsalon.in',
            'instagram_url' => 'https://www.instagram.com/glow',
            'files' => [
                new UploadedFile(base_path('tests/fixtures/menu.pdf'), 'menu.pdf', 'application/pdf', null, true),
                UploadedFile::fake()->image('front.jpg', 800, 600),
            ],
        ])->assertSessionHasNoErrors();

        $business = Business::firstOrFail();
        $sources = $business->sources()->get()->keyBy('type');

        $this->assertSame(Source::STATUS_COMPLETED, $sources['text']->status);
        $this->assertSame(Source::STATUS_COMPLETED, $sources['pdf']->status);
        $this->assertStringContainsString('Bridal makeup', $sources['pdf']->extracted_content);
        $this->assertSame(Source::STATUS_COMPLETED, $sources['website']->status);
        $this->assertSame(Source::STATUS_BLOCKED, $sources['social']->status);
        $this->assertStringContainsString('sign', strtolower($sources['social']->error_message));
        $this->assertSame(Source::STATUS_COMPLETED, $sources['image']->status);

        // Owner text is trusted: its email is confirmed. Website/PDF facts await review.
        $this->assertDatabaseHas('business_facts', ['business_id' => $business->id, 'key' => 'email', 'value' => 'hello@glowsalon.in', 'status' => 'confirmed']);
        $this->assertDatabaseHas('business_facts', ['business_id' => $business->id, 'key' => 'phone', 'source_type' => 'website', 'status' => 'extracted']);
        $this->assertDatabaseHas('business_facts', ['business_id' => $business->id, 'key' => 'phone', 'source_type' => 'pdf']);

        $this->assertSame(1, $business->media()->count());
        Storage::disk('local')->assertExists($business->media()->first()->file_path);
    }

    public function test_upload_validation_rejects_wrong_types_and_sizes(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/businesses', [
            'name' => 'Glow', 'city' => 'Mumbai',
            'files' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')],
        ])->assertSessionHasErrors('files.0');

        config(['siteforge.max_upload_kb' => 100]);
        $this->actingAs($user)->post('/businesses', [
            'name' => 'Glow', 'city' => 'Mumbai',
            'files' => [UploadedFile::fake()->image('big.jpg')->size(500)],
        ])->assertSessionHasErrors('files.0');

        // A file renamed to .pdf whose contents are not a PDF is rejected by content sniffing.
        $path = tempnam(sys_get_temp_dir(), 'sf');
        file_put_contents($path, '<?php echo "hi";');
        $fake = new UploadedFile($path, 'fake.pdf', 'application/pdf', null, true);
        $this->actingAs($user)->post('/businesses', ['name' => 'Glow', 'city' => 'Mumbai', 'files' => [$fake]])->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_business_can_be_listed_viewed_edited_and_deleted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $this->websiteFor($business);

        $this->actingAs($user)->get('/businesses')->assertOk()->assertInertia(fn ($p) => $p->component('Businesses/Index')->has('businesses.data', 1));
        $this->actingAs($user)->get("/businesses/{$business->id}")->assertOk()->assertInertia(fn ($p) => $p->component('Businesses/Show'));

        $this->actingAs($user)->put("/businesses/{$business->id}", ['name' => 'Glow Studio', 'city' => 'Thane', 'description' => 'New text about us.'])->assertSessionHasNoErrors();
        $business->refresh();
        $this->assertSame('Glow Studio', $business->name);
        $this->assertDatabaseHas('sources', ['business_id' => $business->id, 'type' => 'text', 'status' => 'completed']);
        $this->assertDatabaseHas('business_facts', ['business_id' => $business->id, 'key' => 'business_name', 'value' => 'Glow Studio', 'status' => 'confirmed']);

        Storage::disk('local')->put("businesses/{$business->id}/images/x.jpg", 'x');
        $this->actingAs($user)->delete("/businesses/{$business->id}")->assertRedirect('/businesses');
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('websites', 0);
        Storage::disk('local')->assertMissing("businesses/{$business->id}/images/x.jpg");
    }

    public function test_sources_can_be_added_retried_and_removed(): void
    {
        Http::fake(['*example-bakery.com*' => Http::sequence()->push('down', 500)->push('<html><body><p>Fresh bread daily. Phone: 020 2553 1234</p></body></html>', 200, ['Content-Type' => 'text/html'])]);
        $user = User::factory()->create();
        $business = $this->businessFor($user);

        $this->actingAs($user)->post("/businesses/{$business->id}/sources", ['url' => 'example-bakery.com'])->assertSessionHasNoErrors();
        $source = $business->sources()->firstOrFail();
        $this->assertSame(Source::STATUS_FAILED, $source->status);
        $this->assertStringContainsString('500', $source->error_message);

        $this->actingAs($user)->post("/businesses/{$business->id}/sources/{$source->id}/retry")->assertSessionHasNoErrors();
        $this->assertSame(Source::STATUS_COMPLETED, $source->fresh()->status);
        $this->assertDatabaseHas('business_facts', ['source_id' => $source->id, 'key' => 'phone']);

        $this->actingAs($user)->post("/businesses/{$business->id}/sources", ['text' => 'We also make custom cakes.'])->assertSessionHasNoErrors();
        $this->assertSame(2, $business->sources()->count());

        $this->actingAs($user)->delete("/businesses/{$business->id}/sources/{$source->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('business_facts', ['source_id' => $source->id]);

        $this->actingAs($user)->post("/businesses/{$business->id}/sources", [])->assertSessionHasErrors('url');
    }

    public function test_research_page_renders_with_state(): void
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $this->websiteFor($business);
        $this->fact($business, 'phone', '+91 98200 12345', 'extracted');

        $this->actingAs($user)->get("/businesses/{$business->id}/research")->assertOk()->assertInertia(fn ($p) => $p
            ->component('ResearchReview')
            ->has('facts', 1)
            ->where('research.available', false)
            ->where('aiAvailable', false)
            ->has('missing'));
    }
}
