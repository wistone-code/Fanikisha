<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use App\Support\ImportRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Importing a guest list as "[name] [phone] [single/double]" lines, or reading it from a photo. */
class GuestListImportTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_everyday_lines_are_read_as_name_phone_and_card(): void
    {
        $this->assertSame(['Asha Said', '0712 345 678', ''], ImportRows::guestLine('Asha Said 0712 345 678'));
        $this->assertSame(['John Mushi', '+255 754 123 456', ''], ImportRows::guestLine('1. John Mushi +255 754 123 456'));
        $this->assertSame(['Neema & Baraka', '0765432198', 'double'], ImportRows::guestLine('Neema & Baraka 0765432198 double'));
        $this->assertSame(['Rehema Ally', '0713000111', ''], ImportRows::guestLine('0713000111 Rehema Ally'));
        $this->assertSame(['Juma', '0712345678', ''], ImportRows::guestLine('- Juma - 0712345678'));
        $this->assertSame(['Bibi Mkubwa', '', ''], ImportRows::guestLine('Bibi Mkubwa'));
        $this->assertSame(['Zawadi Lyimo', '0712 345 678', 'single'], ImportRows::guestLine('2) Zawadi Lyimo 0712 345 678 Single'));
        $this->assertSame(['Mr & Mrs Mollel', '0754-111-222', 'double'], ImportRows::guestLine('Mr & Mrs Mollel (couple) 0754-111-222'));
        $this->assertSame(['Ali', '255712345678', 'double'], ImportRows::guestLine('Ali 255712345678 wawili'));
        // Columns from Excel or a CSV-style line.
        $this->assertSame(['Peter Kimaro', '0712345678', 'double'], ImportRows::guestLine('Peter Kimaro, 0712345678, double'));
        $this->assertSame(['Peter Kimaro', '0712345678', 'single'], ImportRows::guestLine("Peter Kimaro\t0712345678\tsingle"));
        // A word that only contains "double" is part of the name, not a card type.
        $this->assertSame(['Doublecheck Jones', '0712345678', ''], ImportRows::guestLine('Doublecheck Jones 0712345678'));
    }

    public function test_a_contribution_account_imports_a_list_onto_the_invitation_list_only(): void
    {
        $event = Event::factory()->create(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->post(route('guests.import'), [
            'import_text' => "Asha Said 0712 345 678\n2. John Mushi +255754123456 single\nNeema & Baraka 0765432198 double\n\nBibi Mkubwa",
        ])->assertSessionHas('status');

        $guests = Pledge::where('event_id', $event->id)->orderBy('id')->get();
        $this->assertCount(4, $guests);
        $this->assertTrue($guests->every(fn ($g) => $g->guest_only && $g->invite_token));
        $this->assertSame('+255712345678', $guests[0]->phone);
        $this->assertSame('single', $guests[0]->card_type);
        $this->assertSame('John Mushi', $guests[1]->name);
        $this->assertSame('double', $guests[2]->card_type);
        $this->assertNull($guests[3]->phone);

        // Importing the same list again does not double it.
        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => 'Asha Said 0712 345 678']);
        $this->assertSame(4, Pledge::where('event_id', $event->id)->count());

        // The page shows the import option.
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Import list')->assertSee('[name] [phone number] [single or double]', false);
    }

    public function test_an_ecard_account_imports_free_form_lines(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => "Asha Said 0712345678 double\nBen Mollel"])->assertSessionHas('status');

        $this->assertSame('double', Pledge::where('name', 'Asha Said')->first()->card_type);
        $this->assertFalse((bool) Pledge::where('name', 'Ben Mollel')->first()->guest_only);
    }

    public function test_a_photo_of_a_list_is_read_into_lines_for_checking(): void
    {
        $event = Event::factory()->create(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            ['name' => 'Asha Said', 'phone' => '0712 345 678', 'card' => 'double'],
            ['name' => 'Juma', 'phone' => null, 'card' => null],
            ['name' => '   ', 'phone' => '0700000000', 'card' => 'single'],
        ])]]]]]])]);

        $photo = UploadedFile::fake()->image('list.jpg', 400, 300);

        $this->actingAs($admin)->postJson(route('guests.import-photo'), ['photos' => [$photo]])
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.card', 'double')
            ->assertJsonPath('items.1.phone', null);

        // Reading a photo saves nothing.
        $this->assertSame(0, Pledge::where('event_id', $event->id)->count());

        // Viewers cannot use it, and it needs a photo.
        $viewer = $this->memberOf($event, 'viewer');
        $this->actingAs($viewer)->postJson(route('guests.import-photo'), ['photos' => [$photo]])->assertForbidden();
        $this->actingAs($admin)->postJson(route('guests.import-photo'), [])->assertStatus(422);
    }

    public function test_a_photo_import_without_an_api_key_explains_itself(): void
    {
        [, $admin] = $this->ecardEvent();
        config(['services.gemini.api_key' => null]);

        $this->actingAs($admin)->postJson(route('guests.import-photo'), ['photos' => [UploadedFile::fake()->image('list.jpg')]])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Photo import is not configured — missing Gemini API key.');
    }
}
