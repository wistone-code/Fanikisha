<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\Provider;
use App\Models\ScheduleItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates (or rebuilds) a demo account with a fully populated wedding event, for
 * marketing and sales demos.
 *
 *   php artisan demo:seed
 *   php artisan demo:seed --password=MyDemoPass1 --force
 *
 * Safe by design: every phone number is a made-up 0700 000 0xx number and the
 * event's SMS quota is set to 0, so no real person can ever receive a message
 * from the demo. Running it again wipes and recreates ONLY the demo account.
 */
class SeedDemoEvent extends Command
{
    protected $signature = 'demo:seed
        {--username=demo : Username for the demo login}
        {--email=demo@fanikisha.app : Email for the demo account}
        {--password=Demo@2026 : Password for the demo login}
        {--force : Rebuild without asking if the demo account already exists}';

    protected $description = 'Create a demo account with a fully populated wedding event for marketing';

    public function handle(): int
    {
        $username = (string) $this->option('username');
        $email = (string) $this->option('email');

        $existing = User::whereRaw('LOWER(username) = ?', [strtolower($username)])->first();

        if ($existing) {
            if ($existing->is_super_user) {
                $this->error("'{$username}' is the System Admin — refusing to touch it. Pick another --username.");

                return self::FAILURE;
            }

            if (! $this->option('force') && ! $this->confirm("Account '{$username}' exists. Delete it and its event, then rebuild?")) {
                return self::FAILURE;
            }
        }

        $clash = User::whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($clash) {
            $this->error("Email {$email} belongs to another account. Use --email=... with an unused address.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($existing, $username, $email) {
            // Event rows cascade-delete with their creator, so this clears the old demo completely.
            $existing?->delete();

            $user = User::create([
                'name' => 'Demo Organizer',
                'username' => $username,
                'email' => $email,
                'phone' => null,
                'password' => (string) $this->option('password'),
                'is_super_user' => false,
                'must_change_password' => false,
            ]);

            $event = $this->createEvent($user);
            EventMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => 'admin']);

            $pledges = $this->createPledges($event);
            $this->createProviders($event);
            $this->createSchedule($event);
            $this->createCommittees($event, $pledges);
        });

        $event = Event::where('name', 'Neema & Baraka Wedding')->latest('id')->first();
        $stats = $event->pledges()->selectRaw('COUNT(*) c, SUM(amount) a, SUM(paid) p')->first();

        $this->newLine();
        $this->info('Demo account ready.');
        $this->table(['Login URL', 'Username', 'Password'], [[rtrim(config('app.url'), '/').'/login', $username, $this->option('password')]]);
        $this->line("Event: {$event->name} on {$event->event_date->format('d M Y')} — {$stats->c} pledges, pledged ".number_format($stats->a).' TZS, collected '.number_format($stats->p).' TZS.');
        $this->line('All phone numbers are fake and SMS is disabled (quota 0), so nothing can be sent to real people.');

        return self::SUCCESS;
    }

    private function createEvent(User $user): Event
    {
        return Event::create([
            'name' => 'Neema & Baraka Wedding',
            'event_type' => 'Wedding',
            'place' => 'Mlimani City Conference Hall, Dar es Salaam',
            'event_date' => now()->addDays(45)->toDateString(),
            'pledge_deadline' => now()->addDays(30)->toDateString(),
            'created_by' => $user->id,
            'sms_language' => 'en',
            'sms_quota' => 0,
            'sms_sent_count' => 0,
            'payout_phone' => '+255700000000',
            'payout_network' => 'M-Pesa',
            'couple_threshold_amount' => 200000,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Pledge> */
    private function createPledges(Event $event)
    {
        // name, pledged, paid, rsvp (null = not responded), only meaningful when fully paid
        $rows = [
            ['Amani Mwakyusa', 500000, 500000, 'attending'],
            ['Rehema Kileo', 300000, 300000, 'attending'],
            ['Juma Massawe', 250000, 250000, 'attending'],
            ['Grace Mushi', 200000, 200000, 'attending'],
            ['Hassan Mrema', 200000, 200000, 'not_attending'],
            ['Upendo Shirima', 150000, 150000, 'attending'],
            ['Joseph Kimaro', 150000, 150000, null],
            ['Zawadi Lyimo', 100000, 100000, 'attending'],
            ['Daudi Swai', 100000, 100000, 'attending'],
            ['Mwajuma Said', 100000, 100000, null],
            ['Peter Mollel', 80000, 80000, 'attending'],
            ['Anna Temba', 50000, 50000, 'attending'],
            ['Baraka Nnko', 400000, 250000, null],
            ['Esther Mbwambo', 300000, 200000, null],
            ['Salim Bakari', 250000, 150000, null],
            ['Furaha Mtui', 200000, 120000, null],
            ['Emmanuel Kessy', 200000, 100000, null],
            ['Neema Tarimo', 150000, 90000, null],
            ['Rose Macha', 150000, 50000, null],
            ['Issa Mwinyi', 120000, 60000, null],
            ['Veronica Msuya', 100000, 40000, null],
            ['Goodluck Minja', 100000, 30000, null],
            ['Halima Juma', 100000, 0, null],
            ['Stephen Urio', 100000, 0, null],
            ['Beatrice Ndosi', 80000, 0, null],
            ['Omary Chuwa', 80000, 0, null],
            ['Mariam Kombo', 60000, 0, null],
            ['Elias Mtei', 50000, 0, null],
            ['Lucy Mallya', 50000, 0, null],
            ['Kassim Ngowi', 50000, 0, null],
        ];

        return collect($rows)->values()->map(function ($r, $i) use ($event) {
            [$name, $amount, $paid, $rsvp] = $r;
            $full = $paid >= $amount;

            return Pledge::create([
                'event_id' => $event->id,
                'name' => $name,
                'phone' => sprintf('+2557000000%02d', $i + 1), // fake numbers
                'amount' => $amount,
                'paid' => $paid,
                'pay_token' => Str::random(32),
                'invite_token' => $full ? Str::random(32) : null,
                'card_type' => $amount >= (float) $event->couple_threshold_amount ? 'double' : 'single',
                'rsvp_status' => $full ? $rsvp : null,
                'rsvp_at' => ($full && $rsvp) ? now()->subDays(rand(1, 10)) : null,
            ]);
        });
    }

    private function createProviders(Event $event): void
    {
        $rows = [
            ['Pilau Palace Catering', 'Catering', 3500000, 2000000],
            ['Mlimani Events Hall', 'Venue', 2500000, 2500000],
            ['Picha Moja Studios', 'Photography & Video', 1200000, 600000],
            ['DJ Smooth Sounds', 'MC & Sound', 800000, 300000],
            ['Maua Decor', 'Decoration', 1500000, 500000],
            ['Tamu Cakes', 'Wedding Cake', 450000, 450000],
            ['Safari Cars Hire', 'Transport', 600000, 0],
        ];

        foreach ($rows as $i => [$name, $service, $budget, $paid]) {
            Provider::create([
                'event_id' => $event->id,
                'name' => $name,
                'service' => $service,
                'budget' => $budget,
                'paid' => $paid,
                'phone' => sprintf('+2557000001%02d', $i + 1),
            ]);
        }
    }

    private function createSchedule(Event $event): void
    {
        $day = $event->event_date->copy();

        $rows = [
            ['Send-off party', $day->copy()->subDays(1), '18:00'],
            ['Bridal prep & makeup', $day, '07:00'],
            ['Church ceremony', $day, '10:00'],
            ['Photo session', $day, '12:30'],
            ['Guests arrive at the hall', $day, '15:00'],
            ['Bride & groom entrance', $day, '16:30'],
            ['Dinner served', $day, '18:00'],
            ['Cake cutting', $day, '19:30'],
            ['Dance & entertainment', $day, '20:30'],
        ];

        foreach ($rows as [$title, $date, $time]) {
            ScheduleItem::create(['event_id' => $event->id, 'title' => $title, 'date' => $date->toDateString(), 'time' => $time]);
        }
    }

    private function createCommittees(Event $event, $pledges): void
    {
        $by = fn (string $name) => $pledges->firstWhere('name', $name);

        $groups = [
            'Finance Committee' => [['Amani Mwakyusa', 'Chairperson'], ['Rehema Kileo', 'Treasurer'], ['Juma Massawe', 'Secretary']],
            'Reception Committee' => [['Grace Mushi', 'Chairperson'], ['Zawadi Lyimo', 'Usher'], ['Upendo Shirima', 'Usher']],
            'Food & Drinks Committee' => [['Hassan Mrema', 'Chairperson'], ['Daudi Swai', 'Member'], ['Mwajuma Said', 'Member']],
        ];

        foreach ($groups as $committeeName => $members) {
            $committee = Committee::create(['event_id' => $event->id, 'name' => $committeeName]);

            foreach ($members as [$person, $title]) {
                CommitteeMember::create(['committee_id' => $committee->id, 'pledge_id' => $by($person)->id, 'title' => $title]);
            }
        }
    }
}
