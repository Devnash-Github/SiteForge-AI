<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sources\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A user must never reach another user's business, files, facts or websites. */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_users_cannot_access_business_resources(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $business = $this->businessFor($owner);
        $website = $this->websiteFor($business);
        $section = $website->sections()->create(['type' => 'hero', 'content' => ['headline' => 'Hi'], 'position' => 0, 'is_visible' => true]);
        $source = $business->sources()->create(['type' => 'text', 'extracted_content' => 'x', 'status' => 'completed']);
        $fact = $this->fact($business, 'phone', '9820012345');
        $media = app(MediaService::class)->storeImage($business, UploadedFile::fake()->image('a.jpg'));

        $this->actingAs($intruder);
        $b = $business->id;
        $w = $website->id;

        $this->get("/businesses/{$b}")->assertForbidden();
        $this->get("/businesses/{$b}/research")->assertForbidden();
        $this->put("/businesses/{$b}", ['name' => 'Hacked', 'city' => 'X'])->assertForbidden();
        $this->delete("/businesses/{$b}")->assertForbidden();
        $this->post("/businesses/{$b}/sources", ['text' => 'x'])->assertForbidden();
        $this->delete("/businesses/{$b}/sources/{$source->id}")->assertForbidden();
        $this->post("/businesses/{$b}/facts", ['key' => 'phone', 'value' => '123456789'])->assertForbidden();
        $this->patch("/businesses/{$b}/facts/{$fact->id}", ['status' => 'rejected'])->assertForbidden();
        $this->post("/businesses/{$b}/conflicts/resolve", ['key' => 'phone', 'fact_id' => $fact->id])->assertForbidden();
        $this->deleteJson("/businesses/{$b}/media/{$media->id}")->assertForbidden();
        $this->get("/websites/{$w}/studio")->assertForbidden();
        $this->get("/websites/{$w}/preview")->assertForbidden();
        $this->post("/websites/{$w}/generate")->assertForbidden();
        $this->putJson("/websites/{$w}/sections/{$section->id}", ['content' => ['headline' => 'Hacked']])->assertForbidden();
        $this->putJson("/websites/{$w}/theme", ['theme' => []])->assertForbidden();
        $this->postJson("/websites/{$w}/publish")->assertForbidden();
        $this->delete("/websites/{$w}")->assertForbidden();
        // Private images are not served to other users.
        $this->get("/media/{$media->uuid}")->assertNotFound();

        $this->assertSame('Glow Salon', $business->fresh()->name);
        $this->assertSame('Hi', $section->fresh()->content['headline']);
        $this->assertSame('confirmed', $fact->fresh()->status);
    }

    public function test_nested_resources_must_belong_to_the_parent(): void
    {
        $user = User::factory()->create();
        $mine = $this->businessFor($user);
        $other = $this->businessFor(User::factory()->create());
        $otherFact = $this->fact($other, 'phone', '9820012345');
        $otherSite = $this->websiteFor($other);
        $otherSection = $otherSite->sections()->create(['type' => 'hero', 'content' => ['headline' => 'Hi'], 'position' => 0, 'is_visible' => true]);
        $mySite = $this->websiteFor($mine);

        // Scoped bindings: a fact/section id from someone else's business 404s even under my own business.
        $this->actingAs($user)->patch("/businesses/{$mine->id}/facts/{$otherFact->id}", ['status' => 'rejected'])->assertNotFound();
        $this->actingAs($user)->putJson("/websites/{$mySite->id}/sections/{$otherSection->id}", ['content' => ['headline' => 'x']])->assertNotFound();
        $this->assertSame('confirmed', $otherFact->fresh()->status);
    }

    public function test_dashboard_only_lists_own_websites(): void
    {
        $user = User::factory()->create();
        $this->websiteFor($this->businessFor($user, ['name' => 'Mine']));
        $this->websiteFor($this->businessFor(User::factory()->create(), ['name' => 'Theirs']));

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p
            ->where('stats.total', 1)
            ->has('websites', 1)
            ->where('websites.0.business.name', 'Mine'));
        $this->actingAs($user)->get('/websites')->assertInertia(fn ($p) => $p->has('websites.data', 1));
    }
}
