<?php

namespace Tests\Feature;

use App\Jobs\GenerateWebsiteJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    private function generated(): array
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $this->fact($business, 'phone', '9820012345');
        $this->fact($business, 'service', 'Haircut');
        $website = $this->websiteFor($business);
        GenerateWebsiteJob::dispatchSync($website->id, ['use_ai' => false]);

        return [$user, $business, $website->fresh('sections')];
    }

    public function test_studio_and_preview_render(): void
    {
        [$user, , $website] = $this->generated();
        $this->actingAs($user)->get("/websites/{$website->id}/studio")->assertOk()->assertInertia(fn ($p) => $p
            ->component('WebsiteStudio')
            ->has('renderer.sections', 6)
            ->has('presets', 3)
            ->where('aiAvailable', false));
        $this->actingAs($user)->get("/websites/{$website->id}/preview")->assertOk()->assertInertia(fn ($p) => $p->component('Preview')->has('renderer.sections', 6));
    }

    public function test_section_edits_are_validated_sanitised_and_persisted(): void
    {
        [$user, , $website] = $this->generated();
        $hero = $website->sections->firstWhere('type', 'hero');
        $about = $website->sections->firstWhere('type', 'about');
        $url = "/websites/{$website->id}/sections/{$hero->id}";

        $this->actingAs($user)->putJson($url, ['content' => ['headline' => '<b>New</b> headline', 'subheadline' => 'Sub', 'cta_text' => 'Go', 'cta_url' => '#contact', 'image_id' => null]])
            ->assertOk()->assertJsonPath('renderer.sections.0.content.headline', 'New headline');
        $this->assertSame('New headline', $hero->fresh()->content['headline']);

        $this->actingAs($user)->putJson($url, ['content' => [...$hero->fresh()->content, 'cta_url' => 'javascript:alert(1)']])
            ->assertStatus(422)->assertJsonValidationErrors('content.cta_url');
        $this->assertSame('#contact', $hero->fresh()->content['cta_url']);

        $this->actingAs($user)->putJson($url, ['content' => [...$hero->fresh()->content, 'image_id' => 99999]])->assertStatus(422);

        // Editing one section never touches another.
        $this->assertEquals($about->content, $about->fresh()->content);

        $this->actingAs($user)->putJson($url, ['is_visible' => false])->assertOk();
        $this->assertFalse($hero->fresh()->is_visible);
    }

    public function test_sections_can_be_reordered_added_and_removed(): void
    {
        [$user, , $website] = $this->generated();
        $ids = $website->sections->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($user)->postJson("/websites/{$website->id}/sections/reorder", ['order' => $reversed])->assertOk();
        $this->assertSame($reversed, $website->sections()->pluck('id')->all());

        $this->actingAs($user)->postJson("/websites/{$website->id}/sections/reorder", ['order' => [$ids[0]]])->assertStatus(422);

        $gallery = $website->sections()->where('type', 'gallery')->first();
        $this->actingAs($user)->deleteJson("/websites/{$website->id}/sections/{$gallery->id}")->assertOk();
        $this->assertSame(5, $website->sections()->count());

        $this->actingAs($user)->postJson("/websites/{$website->id}/sections", ['type' => 'gallery'])->assertOk();
        $this->assertSame(6, $website->sections()->count());
        $this->actingAs($user)->postJson("/websites/{$website->id}/sections", ['type' => 'gallery'])->assertStatus(422);
        $this->actingAs($user)->postJson("/websites/{$website->id}/sections", ['type' => 'html'])->assertStatus(422);
    }

    public function test_theme_customisation_is_validated(): void
    {
        [$user, , $website] = $this->generated();
        $theme = ['preset' => 'custom', 'primary' => '#112233', 'secondary' => '#FFEEDD', 'background' => '#FFFFFF', 'text' => '#000000', 'font' => 'playfair', 'button_style' => 'pill'];

        $this->actingAs($user)->putJson("/websites/{$website->id}/theme", ['theme' => $theme])->assertOk();
        $this->assertSame('playfair', $website->fresh()->theme['font']);

        $this->actingAs($user)->putJson("/websites/{$website->id}/theme", ['theme' => [...$theme, 'primary' => 'red; background:url(x)']])->assertStatus(422);
        $this->actingAs($user)->putJson("/websites/{$website->id}/theme", ['theme' => [...$theme, 'font' => 'Comic Sans']])->assertStatus(422);
        $this->assertSame('#112233', $website->fresh()->theme['primary']);
    }

    public function test_section_regeneration_returns_a_proposal_without_saving(): void
    {
        [$user, , $website] = $this->generated();
        $about = $website->sections->firstWhere('type', 'about');
        $before = $about->content;

        // Without AI configured, regeneration explains why.
        $this->actingAs($user)->postJson("/websites/{$website->id}/sections/{$about->id}/regenerate", ['instructions' => 'shorter'])
            ->assertStatus(503)->assertJsonPath('reason', 'not_configured');

        $this->enableGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
            'heading' => 'Who we are', 'body' => 'A short, friendly intro. We have won 3 awards.', 'highlights' => [],
        ]))]);

        $response = $this->actingAs($user)->postJson("/websites/{$website->id}/sections/{$about->id}/regenerate", ['instructions' => 'Make it shorter'])->assertOk();
        $response->assertJsonPath('proposal.heading', 'Who we are');
        $this->assertSame('A short, friendly intro.', $response->json('proposal.body'));
        $this->assertNotEmpty($response->json('removed_claims'));

        $this->assertEquals($before, $about->fresh()->content, 'Nothing is saved until the owner accepts');
        Http::assertSent(fn ($r) => str_contains($r->data()['contents'][0]['parts'][0]['text'], 'Make it shorter'));
    }

    public function test_assets_can_be_uploaded_used_and_deleted(): void
    {
        Storage::fake('local');
        [$user, $business, $website] = $this->generated();

        $response = $this->actingAs($user)->post("/businesses/{$business->id}/media", ['files' => [UploadedFile::fake()->image('team.png', 640, 480)]], ['Accept' => 'application/json'])->assertCreated();
        $mediaId = $response->json('media.0.id');
        $this->actingAs($user)->post("/businesses/{$business->id}/media", ['files' => [UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(422);

        $hero = $website->sections->firstWhere('type', 'hero');
        $this->actingAs($user)->putJson("/websites/{$website->id}/sections/{$hero->id}", ['content' => [...$hero->content, 'image_id' => $mediaId]])->assertOk();

        $media = $business->media()->first();
        $this->actingAs($user)->get("/media/{$media->uuid}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        auth()->logout();
        $this->get("/media/{$media->uuid}")->assertNotFound(); // private until published

        $this->actingAs($user)->deleteJson("/businesses/{$business->id}/media/{$mediaId}")->assertOk();
        $this->assertNull($hero->fresh()->content['image_id']);
        Storage::disk('local')->assertMissing($media->file_path);
    }
}
