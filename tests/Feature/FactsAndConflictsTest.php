<?php

namespace Tests\Feature;

use App\Models\BusinessFact;
use App\Models\User;
use App\Services\Facts\ConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactsAndConflictsTest extends TestCase
{
    use RefreshDatabase;

    public function test_facts_can_be_added_edited_confirmed_rejected_and_restored(): void
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $url = "/businesses/{$business->id}/facts";

        $this->actingAs($user)->post($url, ['key' => 'email', 'value' => 'not-an-email'])->assertSessionHasErrors('value');
        $this->actingAs($user)->post($url, ['key' => 'unknown', 'value' => 'x'])->assertSessionHasErrors('key');
        $this->actingAs($user)->post($url, ['key' => 'service', 'value' => 'Bridal makeup'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_facts', ['key' => 'service', 'value' => 'Bridal makeup', 'status' => 'confirmed', 'source_type' => 'manual']);

        $fact = $this->fact($business, 'phone', '98200 12345', BusinessFact::STATUS_EXTRACTED, null, 'website');
        $this->actingAs($user)->patch("{$url}/{$fact->id}", ['status' => 'confirmed']);
        $this->assertSame('confirmed', $fact->fresh()->status);
        $this->actingAs($user)->patch("{$url}/{$fact->id}", ['status' => 'rejected']);
        $this->assertSame('rejected', $fact->fresh()->status);
        $this->actingAs($user)->patch("{$url}/{$fact->id}", ['status' => 'extracted']);
        $this->assertSame('extracted', $fact->fresh()->status);

        $this->actingAs($user)->patch("{$url}/{$fact->id}", ['value' => '+91 98200 99999']);
        $this->assertSame('+91 98200 99999', $fact->fresh()->value);
        $this->assertSame('9820099999', $fact->fresh()->normalized_value);
        $this->assertSame('confirmed', $fact->fresh()->status, 'Editing a value makes it the owner\'s statement');
    }

    public function test_conflicting_phone_numbers_are_detected_with_their_sources(): void
    {
        $business = $this->businessFor();
        $web = $business->sources()->create(['type' => 'website', 'label' => 'Website', 'status' => 'completed']);
        $pdf = $business->sources()->create(['type' => 'pdf', 'label' => 'menu.pdf', 'status' => 'completed']);
        $this->fact($business, 'phone', '+91 98200 12345', 'extracted', $web->id, 'website');
        $this->fact($business, 'phone', '098200-12345', 'extracted', $pdf->id, 'pdf'); // same number, different format
        $this->fact($business, 'phone', '022 2640 0000', 'extracted', $pdf->id, 'pdf');

        $conflicts = app(ConflictDetector::class)->detect($business);

        $this->assertCount(1, $conflicts);
        $this->assertSame('phone', $conflicts[0]['key']);
        $this->assertCount(2, $conflicts[0]['options'], 'Formatting differences are not conflicts');
        $labels = collect($conflicts[0]['options'])->flatMap(fn ($o) => array_column($o['sources'], 'label'))->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['Website', 'menu.pdf'], $labels);
        $this->assertTrue($conflicts[0]['may_keep_several']);
    }

    public function test_resolving_a_conflict_by_choice_manual_value_or_keep_all(): void
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $a = $this->fact($business, 'address', '12 Hill Road, Bandra', 'extracted', null, 'website');
        $b = $this->fact($business, 'address', '44 Linking Road, Khar', 'extracted', null, 'pdf');
        $url = "/businesses/{$business->id}/conflicts/resolve";

        $this->actingAs($user)->post($url, ['key' => 'address', 'fact_id' => $b->id])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->assertSame([], app(ConflictDetector::class)->detect($business));

        // Manual correction wins over both.
        $this->fact($business, 'opening_hours', 'Mon-Sat 10-8', 'extracted', null, 'website');
        $this->fact($business, 'opening_hours', 'Daily 9-9', 'extracted', null, 'pdf');
        $this->actingAs($user)->post($url, ['key' => 'opening_hours', 'value' => 'Tue-Sun 11:00-20:00'])->assertSessionHasNoErrors();
        $this->assertSame(['Tue-Sun 11:00-20:00'], $business->facts()->where('key', 'opening_hours')->where('status', 'confirmed')->pluck('value')->all());

        // Keep several values only where that makes sense.
        $this->fact($business, 'phone', '9820012345', 'extracted');
        $this->fact($business, 'phone', '02226400000', 'extracted');
        $this->actingAs($user)->post($url, ['key' => 'phone', 'keep_all' => true])->assertSessionHasNoErrors();
        $this->assertSame(2, $business->facts()->where('key', 'phone')->where('status', 'confirmed')->count());
        $this->assertSame([], app(ConflictDetector::class)->detect($business));

        $this->fact($business, 'business_name', 'Glow Spa', 'extracted');
        $this->actingAs($user)->post($url, ['key' => 'business_name', 'keep_all' => true])->assertSessionHasErrors('key');
    }

    public function test_confirm_all_skips_conflicting_and_identity_uncertain_facts(): void
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $this->fact($business, 'service', 'Haircut', 'extracted');
        $this->fact($business, 'email', 'a@glow.in', 'extracted');
        $this->fact($business, 'email', 'b@glow.in', 'extracted');
        $this->fact($business, 'service', 'Massage', 'needs_review');

        $this->actingAs($user)->post("/businesses/{$business->id}/facts/confirm-all")->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $business->facts()->where('value', 'Haircut')->value('status'));
        $this->assertSame(2, $business->facts()->where('key', 'email')->where('status', 'extracted')->count());
        $this->assertSame('needs_review', $business->facts()->where('value', 'Massage')->value('status'));
    }
}
