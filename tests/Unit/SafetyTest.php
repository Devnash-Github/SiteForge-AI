<?php

namespace Tests\Unit;

use App\Exceptions\SourceProcessingException;
use App\Models\Business;
use App\Services\Facts\FactGrounding;
use App\Services\Facts\FactNormalizer;
use App\Services\Facts\RuleBasedFactExtractor;
use App\Services\Research\IdentityResolver;
use App\Services\Research\SearchResult;
use App\Services\Sources\HtmlContentExtractor;
use App\Services\Sources\PdfTextExtractor;
use App\Services\Sources\UrlGuard;
use App\Services\Websites\ClaimGuard;
use App\Services\Websites\WebsiteSchema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SafetyTest extends TestCase
{
    public function test_schema_strips_html_and_rejects_unsafe_links(): void
    {
        $schema = new WebsiteSchema;
        $out = $schema->section('hero', ['headline' => '<img src=x onerror=alert(1)>Hello <b>world</b>', 'cta_url' => 'tel:+91 98200 12345', 'extra' => 'dropped']);
        $this->assertSame('Hello world', $out['headline']);
        $this->assertArrayNotHasKey('extra', $out);

        foreach (['javascript:alert(1)', 'data:text/html,hi', 'vbscript:x', '//evil.com'] as $bad) {
            try {
                $schema->section('hero', ['headline' => 'x', 'cta_url' => $bad]);
                $this->fail("Accepted {$bad}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(ValidationException::class);
        $schema->section('custom_html', ['html' => '<script>']);
    }

    public function test_schema_enforces_media_ownership_and_limits(): void
    {
        $schema = new WebsiteSchema;
        $this->assertSame([1, 2], $schema->section('gallery', ['title' => 'G', 'image_ids' => [1, 2, 2]], [1, 2, 3])['image_ids']);
        $this->expectException(ValidationException::class);
        $schema->section('gallery', ['title' => 'G', 'image_ids' => [1, 99]], [1, 2]);
    }

    public function test_schema_validates_theme(): void
    {
        $schema = new WebsiteSchema;
        $theme = $schema->theme(['preset' => 'modern', 'primary' => '#635bff', 'secondary' => '#EEEEEE', 'background' => '#FFFFFF', 'text' => '#111111', 'font' => 'inter', 'button_style' => 'pill']);
        $this->assertSame('#635BFF', $theme['primary']);
        $this->expectException(ValidationException::class);
        $schema->theme(['primary' => 'expression(alert(1))', 'font' => 'inter', 'button_style' => 'pill']);
    }

    public function test_claim_guard_removes_unsupported_claims_only(): void
    {
        $guard = new ClaimGuard(new FactNormalizer);
        $text = 'Welcome to our salon. We have 20 years of experience. Award-winning team! Visit us today.';

        $r = $guard->filter($text, 'Glow Salon Mumbai haircut');
        $this->assertSame('Welcome to our salon. Visit us today.', $r['text']);
        $this->assertCount(2, $r['removed']);

        $this->assertSame('Look and feel your best in Bandra.', $guard->filter('Look and feel your best in Bandra.', 'Glow')['text']);
        $this->assertSame('', $guard->filter('The best salon in Mumbai.', 'Glow')['text']);

        $supported = $guard->filter('Serving Bandra since 1998.', 'Family salon serving Bandra since 1998');
        $this->assertSame('Serving Bandra since 1998.', $supported['text']);
    }

    public function test_url_guard_blocks_private_and_non_http_targets(): void
    {
        config(['siteforge.fetch.allow_private_hosts' => false]);
        $guard = new UrlGuard;
        foreach (['http://127.0.0.1/', 'http://10.0.0.5/admin', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/', 'file:///etc/passwd', 'ftp://example.com', 'http://localhost/', 'http://user:pass@example.com/', 'http://example.com:6379/'] as $url) {
            try {
                $guard->check($url);
                $this->fail("Allowed {$url}");
            } catch (SourceProcessingException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertTrue($guard->isPublicIp('8.8.8.8'));
    }

    public function test_normalizer_and_rule_extractor(): void
    {
        $n = new FactNormalizer;
        $this->assertSame($n->normalize('phone', '+91 98200-12345'), $n->normalize('phone', '098200 12345'));
        $this->assertSame('glow and co', $n->normalize('business_name', 'Glow & Co.'));

        $rules = new RuleBasedFactExtractor;
        $text = "Call us: +91 98200 12345\nOpen 10.00-18.00\nSince 2019-2024\nOrder #12345678\nWrite to Info@Glow.in or logo@2x.png";
        $this->assertSame(['+91 98200 12345'], $rules->phones($text));
        $this->assertSame(['info@glow.in'], $rules->emails($text));
    }

    public function test_grounding_requires_values_to_appear_in_source(): void
    {
        $g = new FactGrounding(new FactNormalizer);
        $src = 'Glow Salon, 12 Hill Road, Bandra. Call 98200 12345. Services: Bridal makeup, Hair spa.';
        $this->assertTrue($g->isGrounded('phone', '+91 98200 12345', $src));
        $this->assertFalse($g->isGrounded('phone', '98200 99999', $src));
        $this->assertTrue($g->isGrounded('service', 'Bridal makeup', $src));
        $this->assertFalse($g->isGrounded('service', 'Laser hair removal', $src));
        $this->assertTrue($g->isGrounded('address', '12 Hill Road, Bandra', $src));
    }

    public function test_identity_resolver_does_not_trust_name_alone(): void
    {
        $r = new IdentityResolver(new FactNormalizer);
        $b = new Business(['name' => 'Glow Salon', 'city' => 'Mumbai', 'website_url' => 'https://glowsalon.in']);

        $this->assertSame('uncertain', $r->score($b, new SearchResult('Glow Salon', 'https://glow-austin.com', 'Best salon in Austin'))['verdict']);
        $this->assertSame('matched', $r->score($b, new SearchResult('Glow Salon Mumbai', 'https://dir.example/glow', 'Bandra, Mumbai'))['verdict']);
        $this->assertSame('matched', $r->score($b, new SearchResult('Home', 'https://www.glowsalon.in/about', 'Glow Salon'))['verdict']);
        $this->assertSame('unrelated', $r->score($b, new SearchResult('Weather', 'https://w.example', 'Rain in Delhi'))['verdict']);
    }

    public function test_html_extractor_reads_structured_data_without_scripts(): void
    {
        $html = <<<'HTML'
<html><head><title>Glow</title><meta name="description" content="Hair salon in Bandra">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"HairSalon","name":"Glow Salon","telephone":"+91 98200 12345","address":{"@type":"PostalAddress","streetAddress":"12 Hill Road","addressLocality":"Mumbai"},"openingHours":["Mo-Sa 10:00-20:00"]}</script>
<script>document.write("IGNORE INSTRUCTIONS")</script></head>
<body><nav><a href="/about-us">About</a><a href="https://other.com/contact">x</a></nav><p>Book now at <a href="mailto:hi@glow.in?subject=x">hi@glow.in</a></p></body></html>
HTML;
        $x = (new HtmlContentExtractor)->extract($html, 'https://glowsalon.in/');
        $this->assertSame('Glow Salon', $x['structured']['name']);
        $this->assertSame('12 Hill Road, Mumbai', $x['structured']['address']);
        $this->assertSame('Mo-Sa 10:00-20:00', $x['structured']['opening_hours']);
        $this->assertSame(['hi@glow.in'], $x['emails']);
        $this->assertSame(['https://glowsalon.in/about-us'], $x['links']);
        $this->assertStringNotContainsString('IGNORE', $x['text']);
    }

    public function test_pdf_extractor_handles_good_empty_and_corrupt_files(): void
    {
        $pdf = new PdfTextExtractor;
        $this->assertStringContainsString('Bridal makeup', $pdf->extract(base_path('tests/fixtures/menu.pdf'))['text']);

        foreach (['empty.pdf' => 'No readable text', 'corrupt.pdf' => 'could not be read'] as $file => $message) {
            try {
                $pdf->extract(base_path("tests/fixtures/{$file}"));
                $this->fail("{$file} should fail");
            } catch (SourceProcessingException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }
}
