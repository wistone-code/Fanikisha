<?php

namespace Tests\Feature;

use App\Support\PwaVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    protected function tearDown(): void
    {
        PwaVersion::flush();
        parent::tearDown();
    }

    public function test_version_code_changes_when_the_build_or_worker_changes_and_not_otherwise(): void
    {
        $dir = sys_get_temp_dir().'/pwa-'.uniqid();
        File::ensureDirectoryExists($dir.'/build');
        File::put($dir.'/sw.js', '// worker');
        File::put($dir.'/build/manifest.json', '{"app.css":{"file":"assets/app-AAA.css"}}');
        $this->app->usePublicPath($dir);

        PwaVersion::flush();
        $first = PwaVersion::hash();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{10}$/', $first);

        PwaVersion::flush();
        $this->assertSame($first, PwaVersion::hash(), 'same files must give the same code');

        File::put($dir.'/build/manifest.json', '{"app.css":{"file":"assets/app-BBB.css"}}'); // a new deploy's build
        PwaVersion::flush();
        $second = PwaVersion::hash();
        $this->assertNotSame($first, $second);

        File::put($dir.'/sw.js', '// worker changed');
        PwaVersion::flush();
        $this->assertNotSame($second, PwaVersion::hash());

        File::deleteDirectory($dir);
    }

    public function test_signed_in_pages_register_the_worker_with_the_automatic_version_and_offer_install(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $html = $this->actingAs($admin)->get(route('guests.index'))->assertOk()->getContent();

        $this->assertStringContainsString("/sw.js?v=".PwaVersion::hash(), $html);
        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('apple-touch-icon" sizes="180x180"', $html);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $html);
        $this->assertStringContainsString('id="installBtn"', $html);
        $this->assertStringContainsString('Add to Home Screen', $html);
    }

    public function test_login_page_and_guest_invitation_carry_the_app_tags_and_the_invitation_stays_light(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-title', false)
            ->assertDontSee('serviceWorker', false);

        [$event] = $this->ecardEvent();
        $card = $this->guestCard($event);

        $this->get(route('guest.rsvp', $card->invite_token))->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('name="theme-color"', false)
            ->assertDontSee('apple-mobile-web-app-capable', false)
            ->assertDontSee('serviceWorker', false);
    }

    public function test_checkin_page_warns_iphone_users_to_install_and_prepare(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->get(route('checkin.index'))->assertOk()
            ->assertSee('Add to Home Screen')
            ->assertSee('Prepare for offline');
    }

    public function test_manifest_is_complete_and_points_only_to_files_that_exist(): void
    {
        $manifest = json_decode(File::get(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['id', 'name', 'short_name', 'description', 'lang', 'start_url', 'scope', 'display', 'orientation', 'categories', 'icons', 'shortcuts'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest is missing {$key}");
        }

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);

        $purposes = collect($manifest['icons'])->pluck('purpose')->all();
        $this->assertContains('any', $purposes);
        $this->assertContains('maskable', $purposes);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$w] = explode('x', $icon['sizes']);
            $this->assertSame((int) $w, getimagesize(public_path(ltrim($icon['src'], '/')))[0], "{$icon['src']} is not {$icon['sizes']}");
        }

        $this->assertSame('/checkin', $manifest['shortcuts'][0]['url']);
        $this->assertArrayNotHasKey('screenshots', $manifest, 'do not list screenshots until the image files exist');

        foreach (['icons/favicon-32.png', 'icons/apple-touch-icon.png', 'favicon.ico'] as $file) {
            $this->assertFileExists(public_path($file));
        }
        $this->assertSame(180, getimagesize(public_path('icons/apple-touch-icon.png'))[0]);
    }

    public function test_service_worker_takes_its_version_from_the_address_and_never_stores_private_pages(): void
    {
        $sw = File::get(public_path('sw.js'));

        $this->assertStringContainsString("searchParams.get('v')", $sw);
        $this->assertStringNotContainsString('CACHE_VERSION', $sw);
        $this->assertStringContainsString('LEGACY_RUNTIME_PREFIX', $sw, 'phones updating from the old worker must keep their saved check-in page');
        $this->assertStringContainsString('pruneBuildFiles', $sw);

        // Only the check-in page, static folders and fonts may ever be put in a cache.
        $this->assertStringNotContainsString('/pledges', $sw);
        $this->assertStringNotContainsString('/finance', $sw);
        $this->assertStringNotContainsString('indexedDB', $sw, 'the worker must never touch the offline check-in database');
    }

    public function test_offline_page_is_bilingual_and_can_open_a_saved_checkin(): void
    {
        $html = File::get(public_path('offline.html'));

        $this->assertStringContainsString("caches.match('/checkin'", $html);
        $this->assertStringContainsString('Open check-in (saved on this phone)', $html);
        $this->assertStringContainsString('Huna mtandao', $html);
        $this->assertStringContainsString('Jaribu tena', $html);
        $this->assertStringContainsString('/icons/icon-192.png', $html);
        $this->assertStringContainsString('#1F3A52', $html);
        $this->assertStringNotContainsString('fonts.googleapis', $html, 'the offline page must not need the network');
    }
}
