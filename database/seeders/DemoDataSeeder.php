<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\ContactChannel;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\Direction;
use App\Enums\EnquiryStatus;
use App\Enums\InteractionType;
use App\Enums\LifecycleStage;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Enums\QuoteStatus;
use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Enums\Role;
use App\Enums\SupplierCategory;
use App\Enums\TaskType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Enums\TravelStyle;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Feedback;
use App\Models\Interaction;
use App\Models\Quote;
use App\Models\SavedView;
use App\Models\ScheduledReport;
use App\Models\Segment;
use App\Models\ServiceTicket;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\LifecycleService;
use App\Services\QuoteService;
use App\Services\TaskAutomationService;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic demo data for WanderLink Travel, generated from a fixed random
 * seed and relative to today's date so dashboards always look current.
 */
class DemoDataSeeder extends Seeder
{
    private Generator $faker;

    /** @var Collection<int, User> */
    private Collection $consultants;

    private array $users = [];

    /** @var Collection<string, Supplier> */
    private Collection $suppliers;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    private array $loyal = [];

    private Carbon $now;

    private const KENYAN_FIRST = ['Achieng', 'Wanjiku', 'Otieno', 'Kamau', 'Njeri', 'Mwangi', 'Chebet', 'Kiprop', 'Akinyi', 'Omondi', 'Wambui', 'Mutua', 'Nyambura', 'Kibet', 'Adhiambo', 'Ouma', 'Muthoni', 'Njoroge', 'Atieno', 'Kariuki', 'Jepkosgei', 'Barasa', 'Nasimiyu', 'Wafula', 'Makena', 'Gitau', 'Awino', 'Onyango', 'Zawadi', 'Baraka', 'Imani', 'Amani', 'Faith', 'Brian', 'Kevin', 'Mercy', 'Dennis', 'Joy', 'Victor', 'Esther', 'Peter', 'Lucy', 'Samuel', 'Ruth', 'Collins', 'Sharon', 'Eric', 'Caroline'];

    private const KENYAN_LAST = ['Odhiambo', 'Kamau', 'Wanjiru', 'Mwangi', 'Otieno', 'Kiprotich', 'Njoroge', 'Ochieng', 'Mutua', 'Wekesa', 'Chege', 'Kimani', 'Onyango', 'Karanja', 'Kipchumba', 'Omondi', 'Nyaga', 'Muriuki', 'Macharia', 'Koech', 'Waweru', 'Njenga', 'Kiplagat', 'Owino', 'Mbugua', 'Achola', 'Rotich', 'Gathoni'];

    private const KENYAN_CITIES = ['Nairobi', 'Nairobi', 'Nairobi', 'Nairobi', 'Nairobi', 'Mombasa', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Nyeri', 'Malindi', 'Kiambu'];

    /** [first names, last names, city, country, nationality, phone prefix] */
    private const INTERNATIONAL = [
        [['Olivia', 'Jack', 'Charlotte', 'Liam', 'Mia', 'Noah'], ['Smith', 'Nguyen', 'Brown', 'Wilson', 'Taylor'], 'Sydney', 'Australia', 'Australian', '+614'],
        [['Chloe', 'Ethan', 'Grace', 'Lucas'], ['Walker', 'Martin', 'Harris'], 'Melbourne', 'Australia', 'Australian', '+614'],
        [['Amelia', 'Oliver', 'Harry', 'Isla'], ['Jones', 'Patel', 'Evans', 'Clarke'], 'London', 'United Kingdom', 'British', '+447'],
        [['Aisha', 'Omar', 'Fatima'], ['Al Mansoori', 'Haddad', 'Rahman'], 'Dubai', 'United Arab Emirates', 'Emirati', '+9715'],
        [['Moses', 'Sarah', 'Ivan'], ['Mugisha', 'Nakato', 'Ssempala'], 'Kampala', 'Uganda', 'Ugandan', '+2567'],
        [['Neema', 'Juma', 'Rehema'], ['Mushi', 'Mollel', 'Kimaro'], 'Dar es Salaam', 'Tanzania', 'Tanzanian', '+2557'],
        [['Priya', 'Arjun', 'Ananya'], ['Shah', 'Mehta', 'Iyer'], 'Mumbai', 'India', 'Indian', '+919'],
        [['Lena', 'Felix', 'Hannah'], ['Müller', 'Schmidt', 'Weber'], 'Berlin', 'Germany', 'German', '+4915'],
    ];

    private const COMPANIES = ['Savannah Tech Ltd', 'Rift Valley Breweries', 'Kilima Engineering', 'Mombasa Port Logistics', 'Jua Kali Solar', 'Tausi Pharmaceuticals', 'Baobab Legal LLP', 'Umoja Microfinance', 'Nyati Construction', 'Lakeview Hospital', 'Pwani Fresh Produce', 'Summit Insurance Brokers'];

    private const GROUPS = ['St. Andrew\'s Church Youth', 'Karen Hills School Class of 2026', 'Otieno–Wanjiku Wedding Party', 'Nairobi Rotary Club', 'Strathmore Alumni Hikers', 'Kisumu Women in Business', 'Lenana School Geography Trip', 'Mombasa Golf Society', 'Nakuru SDA Choir', 'Eldoret Runners Club', 'Kenya Nurses Association', 'Thika Road Cyclists'];

    /** destination => [style, min pp, max pp, flight?, visa?, region] */
    private const DESTINATIONS = [
        'Maasai Mara' => [TravelStyle::Safari, 41000, 135000, true, false, 'mara'],
        'Diani Beach' => [TravelStyle::Beach, 30000, 90000, true, false, 'coast'],
        'Zanzibar' => [TravelStyle::Beach, 67000, 165000, true, false, 'zanzibar'],
        'Dubai' => [TravelStyle::Family, 90000, 225000, true, true, 'dubai'],
        'Cape Town' => [TravelStyle::Culture, 112000, 240000, true, false, 'capetown'],
        'Rwanda Gorilla Trek' => [TravelStyle::Adventure, 187000, 375000, true, false, 'rwanda'],
        'Seychelles' => [TravelStyle::Honeymoon, 225000, 450000, true, false, 'islands'],
        'Mauritius' => [TravelStyle::Honeymoon, 187000, 375000, true, false, 'islands'],
        'London' => [TravelStyle::Business, 187000, 337000, true, true, 'europe'],
        'Amboseli' => [TravelStyle::Safari, 37000, 112000, false, false, 'mara'],
        'Lamu' => [TravelStyle::Culture, 33000, 82000, true, false, 'coast'],
        'Serengeti' => [TravelStyle::Safari, 135000, 300000, true, false, 'mara'],
        'Bangkok & Phuket' => [TravelStyle::Beach, 135000, 262000, true, true, 'asia'],
        'Istanbul' => [TravelStyle::Culture, 112000, 210000, true, true, 'europe'],
        'Mount Kenya' => [TravelStyle::Adventure, 26000, 67000, false, false, 'mara'],
        'Johannesburg' => [TravelStyle::Business, 67000, 135000, true, false, 'capetown'],
    ];

    private const DESTINATION_WEIGHTS = ['Maasai Mara' => 18, 'Diani Beach' => 16, 'Zanzibar' => 12, 'Dubai' => 10, 'Cape Town' => 5, 'Rwanda Gorilla Trek' => 3, 'Seychelles' => 3, 'Mauritius' => 4, 'London' => 3, 'Amboseli' => 7, 'Lamu' => 5, 'Serengeti' => 3, 'Bangkok & Phuket' => 3, 'Istanbul' => 2, 'Mount Kenya' => 4, 'Johannesburg' => 2];

    private const LOST_REASONS = ['Price too high', 'Booked with another agency', 'Trip postponed', 'Visa not approved', 'No response after quote', 'Changed destination', 'Booked directly with airline'];

    public function run(): void
    {
        $this->now = now();
        $this->faker = \Faker\Factory::create('en_US');
        $this->faker->seed(2026);
        mt_srand(2026);

        activity()->disableLogging();
        // The seeder is a one-off script; N+1 protection is for the app itself.
        $preventLazy = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        $this->seedUsers();
        $this->seedTags();
        $this->seedSuppliers();
        $this->seedHistoricCustomers();
        $this->seedEnquiriesAndBookings();
        $this->seedDemoMoments();
        $this->seedTickets();
        $this->seedFeedback();
        $this->seedTasks();
        $this->seedLifecycle();
        $this->seedMarketing();
        $this->seedReportsAndViews();

        activity()->enableLogging();
        $this->seedAuditTrail();
        Auth::logout();
        Model::preventLazyLoading($preventLazy);
    }

    /* ------------------------------------------------------------------ */

    private function seedUsers(): void
    {
        $staff = [
            ['David Mwangi', 'owner@wanderlink.test', Role::Owner, 'Managing Director', 'amber'],
            ['Faith Njoroge', 'manager@wanderlink.test', Role::Manager, 'Operations Manager', 'violet'],
            ['Achieng Otieno', 'consultant@wanderlink.test', Role::Consultant, 'Senior Travel Consultant', 'teal'],
            ['Brian Kiprop', 'brian@wanderlink.test', Role::Consultant, 'Travel Consultant', 'sky'],
            ['Mercy Wambui', 'mercy@wanderlink.test', Role::Consultant, 'Travel Consultant', 'rose'],
            ['Kevin Omondi', 'kevin@wanderlink.test', Role::Consultant, 'Groups & Corporate Consultant', 'indigo'],
            ['Zawadi Mutua', 'marketing@wanderlink.test', Role::Marketing, 'Marketing Officer', 'emerald'],
            ['Grace Wekesa', 'support@wanderlink.test', Role::Support, 'Customer Care Lead', 'sky'],
        ];

        $password = Hash::make('password');

        foreach ($staff as [$name, $email, $role, $title, $color]) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'job_title' => $title,
                'phone' => '+2547'.$this->faker->numerify('########'),
                'avatar_color' => $color,
                'email_verified_at' => $this->now->copy()->subYear(),
            ]);
            $user->assignRole($role->value);
            $this->users[$email] = $user;
        }

        $this->consultants = collect($this->users)->filter(fn (User $u) => $u->hasRole(Role::Consultant->value))->values();
    }

    private function pickConsultant(?CustomerType $type = null): User
    {
        // Kevin handles most groups/corporates; Achieng carries the biggest book.
        if ($type && $type !== CustomerType::Individual && mt_rand(1, 100) <= 60) {
            return $this->users['kevin@wanderlink.test'];
        }

        return $this->weighted([
            'consultant@wanderlink.test' => 36,
            'brian@wanderlink.test' => 24,
            'mercy@wanderlink.test' => 24,
            'kevin@wanderlink.test' => 16,
        ], fn ($email) => $this->users[$email]);
    }

    private function seedTags(): void
    {
        foreach ([
            ['Honeymooners', 'rose'], ['Corporate account', 'violet'], ['Church group', 'amber'], ['School trip', 'sky'],
            ['Frequent flyer', 'teal'], ['Price sensitive', 'slate'], ['Referral champion', 'emerald'], ['Needs visa help', 'indigo'],
            ['Diaspora', 'sky'], ['Accessibility needs', 'amber'],
        ] as [$name, $color]) {
            Tag::create(['name' => $name, 'color' => $color]);
        }
    }

    private function seedSuppliers(): void
    {
        $rows = [
            ['Kenya Airways', SupplierCategory::Airline, 7, 4.2],
            ['Emirates', SupplierCategory::Airline, 7, 4.7],
            ['Qatar Airways', SupplierCategory::Airline, 6, 4.6],
            ['Safarilink Aviation', SupplierCategory::Airline, 9, 4.4],
            ['Jambojet', SupplierCategory::Airline, 8, 3.9],
            ['Savannah Crest Lodges', SupplierCategory::Hotel, 15, 4.6],
            ['Coral Reef Resorts Diani', SupplierCategory::Hotel, 14, 4.3],
            ['Spice Island Retreats', SupplierCategory::Hotel, 16, 4.5],
            ['Palm Horizon Hotel Dubai', SupplierCategory::Hotel, 12, 4.4],
            ['Table Bay Suites', SupplierCategory::Hotel, 13, 4.2],
            ['Indian Ocean Villas', SupplierCategory::Hotel, 18, 4.8],
            ['Big Five Trails Safaris', SupplierCategory::TourOperator, 18, 4.7],
            ['Rift Valley Expeditions', SupplierCategory::TourOperator, 15, 4.1],
            ['Gorilla Highlands Tours', SupplierCategory::TourOperator, 17, 4.8],
            ['Swahili Coast Excursions', SupplierCategory::TourOperator, 20, 4.0],
            ['SafeJourney Insurance', SupplierCategory::Insurer, 25, 4.3],
            ['Amani Travel Assurance', SupplierCategory::Insurer, 22, 3.8],
            ['Nairobi Executive Transfers', SupplierCategory::Transfer, 20, 4.5],
            ['Coastline Shuttles', SupplierCategory::Transfer, 18, 3.7],
            ['VisaEase Kenya', SupplierCategory::VisaAgent, 30, 4.1],
        ];

        $this->suppliers = collect($rows)->mapWithKeys(function ($row) {
            [$name, $category, $commission, $rating] = $row;
            $contact = $this->faker->randomElement(self::KENYAN_FIRST).' '.$this->faker->randomElement(self::KENYAN_LAST);

            return [$name => Supplier::create([
                'name' => $name,
                'category' => $category,
                'contact_name' => $contact,
                'email' => 'reservations@'.str($name)->slug()->replace('-', '').'.example',
                'phone' => '+2547'.$this->faker->numerify('########'),
                'commission_rate' => $commission,
                'rating' => $rating,
            ])];
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Customers                                                          */
    /* ------------------------------------------------------------------ */

    private function makeCustomer(Carbon $createdAt, ?CustomerSource $source = null, ?CustomerType $type = null): Customer
    {
        $type ??= $this->weighted(['individual' => 80, 'corporate' => 11, 'group' => 9], fn ($t) => CustomerType::from($t));
        $international = $type === CustomerType::Individual && mt_rand(1, 100) <= 24;

        if ($international) {
            [$firsts, $lasts, $city, $country, $nationality, $prefix] = $this->faker->randomElement(self::INTERNATIONAL);
            $first = $this->faker->randomElement($firsts);
            $last = $this->faker->randomElement($lasts);
            $phone = $prefix.$this->faker->numerify('########');
        } else {
            $first = $this->faker->randomElement(self::KENYAN_FIRST);
            $last = $this->faker->randomElement(self::KENYAN_LAST);
            $city = $this->faker->randomElement(self::KENYAN_CITIES);
            $country = 'Kenya';
            $nationality = 'Kenyan';
            $phone = '+2547'.$this->faker->numerify('########');
        }

        $company = match ($type) {
            CustomerType::Corporate => $this->uniqueFrom(self::COMPANIES, 'companies'),
            CustomerType::Group => $this->uniqueFrom(self::GROUPS, 'groups'),
            default => null,
        };

        $source ??= $type === CustomerType::Corporate
            ? CustomerSource::Corporate
            : $this->weighted(['walk_in' => 18, 'website' => 22, 'referral' => 18, 'social' => 14, 'phone' => 10, 'whatsapp' => 18], fn ($s) => CustomerSource::from($s));

        $consent = mt_rand(1, 100) <= 68;
        $emailDomain = $company ? str($company)->slug()->replace('-', '')->limit(18, '').'.co.ke' : $this->faker->randomElement(['gmail.com', 'yahoo.com', 'outlook.com', 'gmail.com']);
        $email = str($first.'.'.$last)->ascii()->lower()->replace(' ', '').mt_rand(1, 99).'@'.$emailDomain;

        $customer = Customer::create([
            'type' => $type,
            'first_name' => $first,
            'last_name' => $last,
            'company_name' => $company,
            'email' => (string) $email,
            'phone' => $phone,
            'whatsapp' => mt_rand(1, 100) <= 70 ? $phone : null,
            'date_of_birth' => $this->faker->dateTimeBetween('-68 years', '-21 years'),
            'nationality' => $nationality,
            'city' => $city,
            'country' => $country,
            'passport_number' => mt_rand(1, 100) <= 80 ? strtoupper($this->faker->randomLetter()).$this->faker->numerify('#######') : null,
            'passport_expiry' => mt_rand(1, 100) <= 80 ? $this->faker->dateTimeBetween('+7 months', '+9 years') : null,
            'preferred_contact_channel' => $this->weighted(['whatsapp' => 50, 'phone' => 20, 'email' => 25, 'sms' => 5], fn ($c) => ContactChannel::from($c)),
            'source' => $source,
            'assigned_to' => $this->pickConsultant($type)->id,
            'lifecycle_stage' => LifecycleStage::Lead,
            'marketing_consent' => $consent,
            'consent_at' => $consent ? $createdAt : null,
            'notes' => mt_rand(1, 100) <= 30 ? $this->faker->randomElement([
                'Prefers morning calls. Very responsive on WhatsApp.',
                'Travels every December with extended family.',
                'Ask about loyalty discount before quoting.',
                'Referred by a past customer — thank them.',
                'Always requests window seats and early check-in.',
                'Finance department must approve invoices before payment.',
            ]) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $style = $type === CustomerType::Corporate ? TravelStyle::Business : $this->faker->randomElement(TravelStyle::cases());
        $customer->preference()->create([
            'seat_preference' => $this->faker->randomElement(['window', 'aisle', 'no preference']),
            'meal_preference' => $this->faker->randomElement(['Standard', 'Vegetarian', 'Halal', 'Vegan', 'Gluten-free', 'Standard', 'Standard']),
            'budget_band' => $this->faker->randomElement(['economy', 'mid', 'mid', 'premium', 'luxury']),
            'travel_style' => $style,
            'preferred_airlines' => $this->faker->randomElements(['Kenya Airways', 'Emirates', 'Qatar Airways', 'Safarilink Aviation'], mt_rand(1, 2)),
            'preferred_destinations' => $this->faker->randomElements(array_keys(self::DESTINATIONS), mt_rand(1, 3)),
            'special_needs' => mt_rand(1, 100) <= 8 ? $this->faker->randomElement(['Wheelchair assistance at airports', 'Travelling with an infant', 'Severe nut allergy', 'Requires ground-floor rooms']) : null,
        ]);

        if ($type !== CustomerType::Individual) {
            foreach (range(1, mt_rand(1, 2)) as $i) {
                $name = $this->faker->randomElement(self::KENYAN_FIRST).' '.$this->faker->randomElement(self::KENYAN_LAST);
                $customer->contacts()->create([
                    'name' => $name,
                    'role' => $type === CustomerType::Corporate ? $this->faker->randomElement(['Travel coordinator', 'HR manager', 'Executive assistant', 'Finance officer']) : $this->faker->randomElement(['Group leader', 'Treasurer', 'Trip organiser']),
                    'email' => str($name)->ascii()->lower()->replace(' ', '.').'@'.$emailDomain,
                    'phone' => '+2547'.$this->faker->numerify('########'),
                ]);
            }
        }

        $tags = [];
        if ($type === CustomerType::Corporate) {
            $tags[] = 'Corporate account';
        }
        if ($type === CustomerType::Group) {
            $tags[] = str_contains($company, 'School') ? 'School trip' : (str_contains($company, 'Church') || str_contains($company, 'Choir') ? 'Church group' : null);
        }
        if ($style === TravelStyle::Honeymoon) {
            $tags[] = 'Honeymooners';
        }
        if ($country !== 'Kenya' && mt_rand(1, 100) <= 40) {
            $tags[] = 'Diaspora';
        }
        if ($source === CustomerSource::Referral && mt_rand(1, 100) <= 30) {
            $tags[] = 'Referral champion';
        }
        foreach (['Price sensitive' => 12, 'Frequent flyer' => 10, 'Needs visa help' => 8] as $tag => $chance) {
            if (mt_rand(1, 100) <= $chance) {
                $tags[] = $tag;
            }
        }
        $customer->tags()->sync(Tag::whereIn('name', array_filter($tags))->pluck('id'));

        $this->customers ??= collect();
        $this->customers->push($customer);

        return $customer;
    }

    /** Customers who first travelled with us 13–26 months ago. */
    private function seedHistoricCustomers(): void
    {
        foreach (range(1, 34) as $i) {
            $created = $this->now->copy()->subDays(mt_rand(395, 790));
            $customer = $this->makeCustomer($created);

            $trips = $this->weighted([0 => 40, 1 => 42, 2 => 14, 3 => 4], fn ($n) => (int) $n);
            $date = $created->copy()->addDays(mt_rand(3, 20));

            foreach (range(1, max($trips, 0)) as $t) {
                if ($trips === 0 || $date->gt($this->now->copy()->subDays(380))) {
                    break;
                }
                $this->createHistoricBooking($customer, $date);
                $date = $date->copy()->addDays(mt_rand(90, 200));
            }

            // A handful of loyal customers will keep booking this year.
            if ($i <= 8) {
                $this->loyal[] = $customer->id;
            }
        }

        // One flagship corporate account with lots of travel => VIP.
        $vip = $this->makeCustomer($this->now->copy()->subDays(760), CustomerSource::Corporate, CustomerType::Corporate);
        $date = $vip->created_at->copy()->addDays(10);
        foreach (range(1, 4) as $t) {
            $this->createHistoricBooking($vip, $date, travellers: mt_rand(3, 6));
            $date->addDays(mt_rand(60, 90));
        }
        $this->loyal[] = $vip->id;
    }

    private function createHistoricBooking(Customer $customer, Carbon $bookedAt, ?int $travellers = null): Booking
    {
        $destination = $this->pickDestination($customer);
        [$style, $min, $max] = self::DESTINATIONS[$destination];
        $travellers ??= $this->travellersFor($customer)[0];
        $amount = round(mt_rand($min, $max) * $travellers, -3);
        $start = $bookedAt->copy()->addDays(mt_rand(14, 60));
        $end = $start->copy()->addDays(mt_rand(3, 9));

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'consultant_id' => $customer->assigned_to,
            'destination' => $destination,
            'start_date' => $start,
            'end_date' => $end,
            'total_amount' => $amount,
            'amount_paid' => $amount,
            'currency' => 'KES',
            'payment_status' => PaymentStatus::Paid,
            'status' => $end->lt($this->now) ? BookingStatus::Completed : BookingStatus::Confirmed,
            'created_at' => $bookedAt,
            'updated_at' => $bookedAt,
        ]);

        $this->createPayments($booking, $bookedAt, fully: true);
        $this->interaction($customer, InteractionType::Note, Direction::Outbound, "Booking {$booking->reference} confirmed", "{$destination} for {$travellers} traveller(s). Documents sent.", $bookedAt, $booking);

        return $booking;
    }

    /* ------------------------------------------------------------------ */
    /*  Enquiries, quotes, bookings                                        */
    /* ------------------------------------------------------------------ */

    private function seedEnquiriesAndBookings(): void
    {
        // ~210 across the year plus a fresh batch this week so the "New" column is lively.
        $dates = collect(range(1, 210))->map(fn () => $this->enquiryDate())
            ->merge(collect(range(1, 12))->map(fn () => $this->now->copy()->subHours(mt_rand(1, 96))))
            ->sort()->values();

        foreach ($dates as $createdAt) {
            $customer = $this->customerForEnquiry($createdAt);
            $this->createEnquiry($customer, $createdAt);
        }
    }

    /** Growth + seasonality: more enquiries recently and around Dec / Jul–Aug / Easter. */
    private function enquiryDate(): Carbon
    {
        while (true) {
            $daysAgo = mt_rand(0, 364);
            $date = $this->now->copy()->subDays($daysAgo)->setTime(mt_rand(8, 18), mt_rand(0, 59));
            $growth = 0.55 + 0.45 * (1 - $daysAgo / 365);
            $season = in_array($date->month, [11, 12, 6, 7, 3], true) ? 1.0 : 0.7;
            if (mt_rand(1, 1000) / 1000 <= $growth * $season) {
                return $date;
            }
        }
    }

    private function customerForEnquiry(Carbon $at): Customer
    {
        $existing = $this->customers->filter(fn (Customer $c) => $c->created_at->lt($at->copy()->subDays(7)));
        $roll = mt_rand(1, 100);

        if ($this->customers->count() >= 150 || ($roll <= 38 && $existing->isNotEmpty())) {
            $loyal = $existing->whereIn('id', $this->loyal);
            if ($loyal->isNotEmpty() && mt_rand(1, 100) <= 40) {
                return $loyal->random();
            }

            return $existing->isNotEmpty() ? $existing->random() : $this->customers->random();
        }

        return $this->makeCustomer($at->copy()->subMinutes(mt_rand(5, 90)));
    }

    private function statusForAge(int $daysAgo): EnquiryStatus
    {
        $weights = match (true) {
            $daysAgo > 90 => ['won' => 38, 'lost' => 62],
            $daysAgo > 35 => ['won' => 40, 'lost' => 36, 'negotiating' => 14, 'quoted' => 10],
            $daysAgo > 10 => ['won' => 22, 'lost' => 10, 'negotiating' => 22, 'quoted' => 24, 'contacted' => 16, 'new' => 6],
            default => ['new' => 36, 'contacted' => 34, 'quoted' => 22, 'negotiating' => 8],
        };

        return $this->weighted($weights, fn ($s) => EnquiryStatus::from($s));
    }

    private function createEnquiry(Customer $customer, Carbon $createdAt): Enquiry
    {
        $destination = $this->pickDestination($customer);
        [$style, $min, $max] = self::DESTINATIONS[$destination];
        [$adults, $children] = $this->travellersFor($customer);
        $pp = $customer->type === CustomerType::Group ? mt_rand($min, (int) (($min + $max) / 2)) : mt_rand($min, $max);
        $expected = round($pp * ($adults + $children * 0.6) * ($adults + $children >= 10 ? 0.85 : 1), -3);
        $departure = $createdAt->copy()->addDays(mt_rand(21, 150))->startOfDay();
        $daysAgo = (int) $createdAt->diffInDays($this->now);
        $status = $this->statusForAge($daysAgo);

        // Won deals need a booking date in the past.
        if ($status === EnquiryStatus::Won && $daysAgo < 3) {
            $status = EnquiryStatus::Negotiating;
        }

        $channel = $customer->created_at->gte($createdAt->copy()->subDay())
            ? $customer->source
            : $this->weighted(['whatsapp' => 35, 'phone' => 20, 'walk_in' => 15, 'website' => 20, 'referral' => 10], fn ($s) => CustomerSource::from($s));

        $consultant = User::find($customer->assigned_to);
        $quoteAt = $createdAt->copy()->addHours(mt_rand(4, 72));
        $closedAt = $quoteAt->copy()->addDays(mt_rand(1, 10))->min($this->now->copy()->subHours(2));

        $stageChanged = match ($status) {
            EnquiryStatus::New => $createdAt,
            EnquiryStatus::Contacted => $createdAt->copy()->addHours(mt_rand(1, 30))->min($this->now),
            EnquiryStatus::Quoted, EnquiryStatus::Negotiating => $quoteAt->copy()->min($this->now),
            default => $closedAt,
        };

        // ~30% of open deals have gone quiet: these get the "stale" nudge.
        $lastActivity = $status->isOpen()
            ? (mt_rand(1, 100) <= 30 ? $this->now->copy()->subDays(mt_rand(4, 12))->max($createdAt) : $this->now->copy()->subHours(mt_rand(2, 60))->max($createdAt))
            : $closedAt;

        $enquiry = Enquiry::create([
            'customer_id' => $customer->id,
            'destination' => $destination,
            'departure_date' => $departure,
            'return_date' => $departure->copy()->addDays(mt_rand(3, 9)),
            'travellers_adults' => $adults,
            'travellers_children' => $children,
            'budget' => round($expected * mt_rand(90, 125) / 100, -3),
            'trip_type' => $customer->type === CustomerType::Corporate ? TravelStyle::Business : $style,
            'channel' => $channel,
            'status' => $status,
            'lost_reason' => $status === EnquiryStatus::Lost ? $this->faker->randomElement(self::LOST_REASONS) : null,
            'assigned_to' => $consultant?->id,
            'expected_value' => $expected,
            'probability' => $status->probability(),
            'next_follow_up_at' => $status->isOpen() ? $this->now->copy()->addHours(mt_rand(-72, 96)) : null,
            'stage_changed_at' => $stageChanged,
            'last_activity_at' => $lastActivity,
            'notes' => mt_rand(1, 100) <= 35 ? $this->faker->randomElement([
                'Flexible on dates by a few days.', 'Wants a private vehicle for game drives.', 'Celebrating a 10th anniversary.',
                'Needs invoice in company name.', 'Asked for payment in instalments via M-Pesa.', 'Compare with last year\'s package.',
            ]) : null,
            'created_at' => $createdAt,
            'updated_at' => $lastActivity,
        ]);

        $this->logEnquiryConversation($enquiry, $customer, $consultant, $createdAt, $status, $quoteAt);

        $needsQuote = in_array($status, [EnquiryStatus::Quoted, EnquiryStatus::Negotiating, EnquiryStatus::Won], true)
            || ($status === EnquiryStatus::Lost && mt_rand(1, 100) <= 55);

        if ($needsQuote) {
            $quote = $this->createQuote($enquiry, $customer, $quoteAt->copy()->min($this->now->copy()->subHour()), $status);

            if ($status === EnquiryStatus::Won) {
                $this->createBookingFromQuote($quote, $enquiry, $customer, $closedAt);
            }
        }

        return $enquiry;
    }

    private function logEnquiryConversation(Enquiry $enquiry, Customer $customer, ?User $consultant, Carbon $createdAt, EnquiryStatus $status, Carbon $quoteAt): void
    {
        $inboundType = match ($enquiry->channel) {
            CustomerSource::WhatsApp, CustomerSource::Social => InteractionType::WhatsApp,
            CustomerSource::Phone => InteractionType::Call,
            CustomerSource::Website, CustomerSource::Corporate => InteractionType::Email,
            default => InteractionType::Meeting,
        };

        $travellers = $enquiry->travellers();
        $this->interaction($customer, $inboundType, Direction::Inbound, "Enquiry: {$enquiry->destination}",
            $this->faker->randomElement([
                "Hi, we're looking at {$enquiry->destination} around ".fdate($enquiry->departure_date)." for {$travellers}. What packages do you have?",
                "Interested in a {$enquiry->trip_type?->label()} trip to {$enquiry->destination}. Budget around ".money($enquiry->budget).'.',
                "Could you send options for {$enquiry->destination}? {$travellers} travellers, flexible dates.",
            ]), $createdAt, $enquiry, $consultant);

        if ($status !== EnquiryStatus::New && mt_rand(1, 100) <= 25) {
            $this->interaction($customer, $this->faker->randomElement([InteractionType::Call, InteractionType::WhatsApp]), Direction::Outbound, 'Discovery call',
                $this->faker->randomElement([
                    'Confirmed dates, room configuration and budget. Customer prefers a lodge with a pool.',
                    'Walked through options. Customer wants flights included and airport transfers.',
                    'Discussed visa requirements and passport validity. Sending options tomorrow.',
                ]), $createdAt->copy()->addHours(mt_rand(1, 20))->min($this->now), $enquiry, $consultant);
        }

        if (in_array($status, [EnquiryStatus::Negotiating, EnquiryStatus::Won], true) && mt_rand(1, 100) <= 35) {
            $this->interaction($customer, InteractionType::WhatsApp, Direction::Inbound, 'Quote feedback',
                $this->faker->randomElement([
                    'Can you do a better price if we pay in full now?',
                    'Looks good — can we swap the hotel for something on the beach?',
                    'Checking with my spouse, will revert by Friday.',
                    'Is the insurance optional?',
                ]), $quoteAt->copy()->addDays(mt_rand(1, 3))->min($this->now), $enquiry, $consultant);
        }
    }

    private function createQuote(Enquiry $enquiry, Customer $customer, Carbon $at, EnquiryStatus $status): Quote
    {
        [$style, , , $flight, $visa, $region] = self::DESTINATIONS[$enquiry->destination];
        $currency = $customer->country === 'Australia' ? 'AUD' : ($customer->country !== 'Kenya' && mt_rand(1, 100) <= 50 ? 'USD' : 'KES');
        $rate = \App\Enums\Currency::from($currency)->toKes();

        $quote = Quote::create([
            'enquiry_id' => $enquiry->id,
            'currency' => $currency,
            'valid_until' => $at->copy()->addDays(14),
            'status' => match ($status) {
                EnquiryStatus::Won => QuoteStatus::Accepted,
                EnquiryStatus::Lost => QuoteStatus::Rejected,
                default => $at->copy()->addDays(14)->lt($this->now) ? QuoteStatus::Expired : QuoteStatus::Sent,
            },
            'sent_at' => $at,
            'created_by' => $enquiry->assigned_to,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $shares = array_filter([
            'flight' => $flight ? 0.42 : null,
            'hotel' => 0.36,
            'tour' => in_array($style, [TravelStyle::Safari, TravelStyle::Adventure, TravelStyle::Culture], true) ? 0.14 : null,
            'transfer' => 0.04,
            'insurance' => mt_rand(1, 100) <= 65 ? 0.03 : null,
            'visa' => $visa ? 0.02 : null,
        ]);
        $sum = array_sum($shares);
        $markups = ['flight' => 8, 'hotel' => 15, 'tour' => 18, 'transfer' => 20, 'insurance' => 25, 'visa' => 30];
        $travellers = $enquiry->travellers();

        foreach ($shares as $type => $share) {
            $price = $enquiry->expected_value * $share / $sum / $rate;
            $cost = round($price / (1 + $markups[$type] / 100), $currency === 'KES' ? -2 : 0);
            [$supplier, $description] = $this->supplierFor($type, $region, $enquiry->destination, $travellers);

            $quote->items()->create([
                'supplier_id' => $supplier?->id,
                'type' => $type,
                'description' => $description,
                'cost' => $cost,
                'markup' => $markups[$type],
                'price' => QuoteService::priceFor($cost, $markups[$type]),
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $quote->update(['total_amount' => $quote->items()->sum('price')]);

        if (! $status->isOpen()) {
            return $quote;
        }

        $this->interaction($customer, InteractionType::Email, Direction::Outbound, "Quote {$quote->reference} sent",
            "Quote for {$enquiry->destination} totalling ".money($quote->total_amount, $quote->currency).'.', $at, $enquiry, User::find($enquiry->assigned_to));

        return $quote;
    }

    private function supplierFor(string $type, string $region, string $destination, int $travellers): array
    {
        $s = fn (string $name) => $this->suppliers[$name];

        return match ($type) {
            'flight' => in_array($region, ['mara', 'coast'], true)
                ? [$s($this->faker->randomElement(['Safarilink Aviation', 'Jambojet', 'Kenya Airways'])), "Return flights Nairobi – {$destination} ({$travellers} pax)"]
                : [$s($this->faker->randomElement(['Kenya Airways', 'Emirates', 'Qatar Airways'])), "Return economy flights NBO – {$destination} ({$travellers} pax)"],
            'hotel' => [$s(match ($region) {
                'mara' => 'Savannah Crest Lodges',
                'coast' => 'Coral Reef Resorts Diani',
                'zanzibar' => 'Spice Island Retreats',
                'dubai' => 'Palm Horizon Hotel Dubai',
                'capetown' => 'Table Bay Suites',
                'islands' => 'Indian Ocean Villas',
                default => $this->faker->randomElement(['Palm Horizon Hotel Dubai', 'Table Bay Suites']),
            }), "Accommodation, {$destination} — half board"],
            'tour' => [$s(match ($region) {
                'mara' => $this->faker->randomElement(['Big Five Trails Safaris', 'Rift Valley Expeditions']),
                'rwanda' => 'Gorilla Highlands Tours',
                'coast', 'zanzibar' => 'Swahili Coast Excursions',
                default => 'Rift Valley Expeditions',
            }), $region === 'rwanda' ? 'Gorilla trekking permit and guided trek' : "Guided excursions & game drives, {$destination}"],
            'transfer' => [$s($region === 'coast' || $region === 'zanzibar' ? 'Coastline Shuttles' : 'Nairobi Executive Transfers'), 'Airport transfers (return)'],
            'insurance' => [$s($this->faker->randomElement(['SafeJourney Insurance', 'Amani Travel Assurance'])), 'Comprehensive travel insurance'],
            'visa' => [$s('VisaEase Kenya'), "Visa processing — {$destination}"],
        };
    }

    private function createBookingFromQuote(Quote $quote, Enquiry $enquiry, Customer $customer, Carbon $bookedAt): Booking
    {
        $start = $enquiry->departure_date;
        $end = $enquiry->return_date;

        $status = match (true) {
            mt_rand(1, 100) <= 4 => BookingStatus::Cancelled,
            $end->lt($this->now->copy()->startOfDay()) => BookingStatus::Completed,
            $start->lte($this->now) => BookingStatus::Travelling,
            default => BookingStatus::Confirmed,
        };

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'consultant_id' => $enquiry->assigned_to,
            'destination' => $enquiry->destination,
            'start_date' => $start,
            'end_date' => $end,
            'total_amount' => $quote->total_amount,
            'amount_paid' => 0,
            'currency' => $quote->currency,
            'payment_status' => PaymentStatus::Pending,
            'status' => $status,
            'created_at' => $bookedAt,
            'updated_at' => $bookedAt,
        ]);

        if ($status === BookingStatus::Cancelled) {
            $this->createPayments($booking, $bookedAt, fully: false);
            $booking->update(['payment_status' => PaymentStatus::Refunded]);
        } else {
            $this->createPayments($booking, $bookedAt, fully: in_array($status, [BookingStatus::Completed, BookingStatus::Travelling], true) || $start->lte($this->now->copy()->addDays(21)));
        }

        $this->interaction($customer, InteractionType::Note, Direction::Outbound, "Booking {$booking->reference} confirmed",
            "Quote {$quote->reference} accepted. Deposit requested.", $bookedAt, $booking, User::find($enquiry->assigned_to));

        if (in_array($status, [BookingStatus::Completed, BookingStatus::Travelling], true) && mt_rand(1, 100) <= 30) {
            $this->interaction($customer, InteractionType::WhatsApp, Direction::Outbound, 'Travel documents sent',
                'E-tickets, vouchers and itinerary shared. Wished them a safe trip!', $start->copy()->subDays(mt_rand(2, 5))->setTime(11, 0)->min($this->now), $booking, User::find($enquiry->assigned_to));
        }

        return $booking;
    }

    private function createPayments(Booking $booking, Carbon $bookedAt, bool $fully): void
    {
        $total = (float) $booking->total_amount;
        $method = fn () => $this->weighted(['mpesa' => 45, 'bank' => 25, 'card' => 25, 'cash' => 5], fn ($m) => $m);
        $ref = fn ($m) => match ($m) {
            'mpesa' => 'S'.strtoupper($this->faker->bothify('?#??##?#?')),
            'card' => 'CARD-'.$this->faker->numerify('####'),
            'bank' => 'EFT'.$this->faker->numerify('######'),
            default => 'RCPT-'.$this->faker->numerify('#####'),
        };

        $depositPct = mt_rand(30, 50) / 100;
        $roll = mt_rand(1, 100);

        if (! $fully && $roll <= 25) {
            return; // awaiting deposit
        }

        $deposit = round($total * $depositPct, -2);
        $m = $method();
        $booking->payments()->create(['amount' => $deposit, 'method' => $m, 'reference' => $ref($m), 'paid_at' => $bookedAt->copy()->addHours(mt_rand(1, 48))->min($this->now)]);
        $paid = $deposit;

        if ($fully) {
            $m = $method();
            $balanceDate = $booking->start_date->copy()->subDays(mt_rand(10, 30))->max($bookedAt)->min($this->now);
            $booking->payments()->create(['amount' => $total - $deposit, 'method' => $m, 'reference' => $ref($m), 'paid_at' => $balanceDate]);
            $paid = $total;
        }

        $booking->update([
            'amount_paid' => $paid,
            'payment_status' => $paid >= $total ? PaymentStatus::Paid : PaymentStatus::Partial,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  "My Day" moments for the demo                                      */
    /* ------------------------------------------------------------------ */

    private function seedDemoMoments(): void
    {
        $achieng = $this->users['consultant@wanderlink.test'];

        // Travellers departing within 7 days.
        $upcoming = Booking::where('status', BookingStatus::Confirmed)->where('start_date', '>', $this->now)
            ->orderBy('start_date')->get()->groupBy('consultant_id');

        foreach ($this->consultants as $consultant) {
            foreach (($upcoming->get($consultant->id) ?? collect())->take($consultant->is($achieng) ? 3 : 1)->values() as $i => $booking) {
                $nights = $booking->nights();
                $start = $this->now->copy()->addDays(2 + $i * 2)->startOfDay();
                $booking->update(['start_date' => $start, 'end_date' => $start->copy()->addDays($nights)]);
                $booking->quote?->enquiry?->update(['departure_date' => $start, 'return_date' => $start->copy()->addDays($nights)]);
            }
        }

        // Passports expiring within 6 months (some with trips coming up!).
        $travelling = Customer::whereIn('id', Booking::where('start_date', '>', $this->now)->pluck('customer_id'))->whereNotNull('passport_number')->inRandomOrder()->limit(3)->get();
        $others = Customer::where('assigned_to', $achieng->id)->whereNotNull('passport_number')->inRandomOrder()->limit(4)->get();
        foreach ($travelling->merge($others)->merge(Customer::whereNotNull('passport_number')->inRandomOrder()->limit(5)->get()) as $customer) {
            $customer->update(['passport_expiry' => $this->now->copy()->addDays(mt_rand(20, 170))]);
        }

        // Birthdays today.
        foreach (Customer::where('assigned_to', $achieng->id)->where('type', CustomerType::Individual)->inRandomOrder()->limit(2)->get() as $customer) {
            $customer->update(['date_of_birth' => $this->now->copy()->subYears(mt_rand(25, 60))]);
        }
        $other = Customer::where('assigned_to', '!=', $achieng->id)->where('type', CustomerType::Individual)->inRandomOrder()->first();
        $other?->update(['date_of_birth' => $this->now->copy()->subYears(41)]);
    }

    /* ------------------------------------------------------------------ */
    /*  Service                                                            */
    /* ------------------------------------------------------------------ */

    private function seedTickets(): void
    {
        $grace = $this->users['support@wanderlink.test'];
        $faith = $this->users['manager@wanderlink.test'];
        $bookings = Booking::with('customer')->where('created_at', '>=', $this->now->copy()->subMonths(8))->get();

        $scenarios = [
            [TicketCategory::LostDocument, Priority::Urgent, 'Passport lost in Dubai — flight home tomorrow', 'Customer called in distress: passport stolen at the hotel. Needs emergency travel document from the embassy and flight change.'],
            [TicketCategory::Complaint, Priority::High, 'Hotel room not as described', 'Customer says the sea-view room was a garden view. Wants partial refund.'],
            [TicketCategory::ChangeRequest, Priority::Normal, 'Change return date by two days', 'Customer wants to extend stay. Check airline change fees and hotel availability.'],
            [TicketCategory::Refund, Priority::High, 'Refund for cancelled tour', 'Tour operator cancelled the Day 3 excursion due to weather. Customer requests refund.'],
            [TicketCategory::General, Priority::Low, 'Request for invoice copy', 'Finance office needs a stamped invoice copy for reimbursement.'],
            [TicketCategory::Complaint, Priority::Normal, 'Late airport pick-up', 'Driver arrived 90 minutes late at JKIA. Customer unhappy.'],
            [TicketCategory::ChangeRequest, Priority::Normal, 'Add a traveller to the booking', 'Customer\'s sister will join. Needs flight and room upgrade to triple.'],
            [TicketCategory::LostDocument, Priority::High, 'Lost yellow fever card', 'Customer cannot find their yellow fever certificate a week before departure.'],
            [TicketCategory::Refund, Priority::Normal, 'Overcharged on card payment', 'Customer says they were charged twice for the deposit.'],
            [TicketCategory::General, Priority::Low, 'Dietary requirements for safari lodge', 'Confirm vegan meals with the lodge.'],
        ];

        foreach (range(0, 24) as $i) {
            [$category, $priority, $subject, $description] = $scenarios[$i % count($scenarios)];
            $booking = $bookings->random();
            $open = $i < 9;
            // Open tickets are recent (some already breaching SLA); resolved ones span six months.
            $created = $open
                ? ($i % 2 === 0 ? $this->now->copy()->subHours(mt_rand(1, 20)) : $this->now->copy()->subDays(mt_rand(1, 3))->subHours(mt_rand(1, 10)))
                : $this->now->copy()->subDays(mt_rand(4, 170))->subHours(mt_rand(1, 10));
            $slaDue = $created->copy()->addHours($priority->slaHours());

            // Make the first scenario Grace's live "angry customer" case.
            if ($i === 0) {
                $created = $this->now->copy()->subHours(2);
                $slaDue = $created->copy()->addHours(4);
            }

            $status = $open ? ($i % 3 === 0 ? TicketStatus::Open : TicketStatus::InProgress) : ($i % 4 === 0 ? TicketStatus::Closed : TicketStatus::Resolved);
            $resolvedAt = $open ? null : $created->copy()->addHours(mt_rand(2, (int) ($priority->slaHours() * 1.4)));

            $ticket = ServiceTicket::create([
                'customer_id' => $booking->customer_id,
                'booking_id' => $booking->id,
                'subject' => $subject,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $i % 5 === 4 ? $faith->id : $grace->id,
                'sla_due_at' => $slaDue,
                'resolved_at' => $resolvedAt,
                'resolution' => $open ? null : $this->faker->randomElement([
                    'Supplier agreed to a partial refund; credited to customer account.',
                    'Change processed. Updated tickets and vouchers sent via WhatsApp.',
                    'Apologised and issued a KES 5,000 voucher for the next trip.',
                    'Emergency travel document obtained; flight rebooked at no cost.',
                    'Invoice copy emailed to finance office.',
                ]),
                'created_at' => $created,
                'updated_at' => $resolvedAt ?? $created,
            ]);

            foreach (range(1, mt_rand(1, 3)) as $n) {
                $ticket->notes()->create([
                    'user_id' => $ticket->assigned_to,
                    'body' => $this->faker->randomElement([
                        'Called the customer to acknowledge and reassure them.',
                        'Emailed supplier for their account of events. Awaiting reply.',
                        'Supplier confirmed — preparing options for the customer.',
                        'Escalated to operations manager for approval.',
                        'Checked the booking file: payment and vouchers are in order.',
                    ]),
                    'created_at' => $created->copy()->addMinutes(30 * $n),
                    'updated_at' => $created->copy()->addMinutes(30 * $n),
                ]);
            }

            $this->interaction($booking->customer, InteractionType::Call, Direction::Inbound, "Ticket {$ticket->reference}: {$subject}", $description, $created, $ticket, $grace);
        }
    }

    private function seedFeedback(): void
    {
        $completed = Booking::with('customer')->where('status', BookingStatus::Completed)
            ->where('end_date', '<', $this->now->copy()->subDays(3))->inRandomOrder()->limit(60)->get();

        foreach ($completed as $booking) {
            $group = $this->weighted(['promoter' => 58, 'passive' => 28, 'detractor' => 14], fn ($g) => $g);
            [$nps, $rating, $comment] = match ($group) {
                'promoter' => [mt_rand(9, 10), 5, $this->faker->randomElement([
                    'Everything was seamless — Achieng thought of every detail!', 'Best safari of our lives. Will book again.',
                    'Great value and super responsive on WhatsApp.', 'The honeymoon suite upgrade was a lovely surprise.', 'Smooth transfers and great hotel choice.',
                ])],
                'passive' => [mt_rand(7, 8), mt_rand(3, 4), $this->faker->randomElement([
                    'Good trip overall, but the flight times were inconvenient.', 'Hotel was fine; food could be better.', 'Nice experience, a bit pricey.',
                ])],
                default => [mt_rand(2, 6), mt_rand(1, 3), $this->faker->randomElement([
                    'Pick-up was late and nobody answered the emergency number.', 'Room did not match the photos.', 'Too many hidden charges at the lodge.',
                ])],
            };

            Feedback::create([
                'customer_id' => $booking->customer_id,
                'booking_id' => $booking->id,
                'nps_score' => $nps,
                'rating' => $rating,
                'comment' => $comment,
                'submitted_at' => $booking->end_date->copy()->addDays(mt_rand(3, 10))->setTime(mt_rand(8, 21), 0)->min($this->now),
            ]);
        }
    }

    private function seedTasks(): void
    {
        foreach (Enquiry::open()->with('customer')->get() as $enquiry) {
            if (mt_rand(1, 100) > 75) {
                continue;
            }

            $due = $this->now->copy()->addDays(mt_rand(-3, 4))->setTime(mt_rand(9, 16), 0);
            Task::create([
                'customer_id' => $enquiry->customer_id,
                'enquiry_id' => $enquiry->id,
                'assigned_to' => $enquiry->assigned_to,
                'created_by' => $enquiry->assigned_to,
                'type' => TaskType::FollowUp,
                'title' => $this->faker->randomElement([
                    "Follow up with {$enquiry->customer->first_name} on {$enquiry->destination}",
                    "Send revised {$enquiry->destination} options",
                    "Check hotel availability for {$enquiry->destination}",
                    "Call {$enquiry->customer->first_name} re: deposit",
                ]),
                'due_at' => $due,
                'priority' => $this->weighted(['low' => 10, 'normal' => 55, 'high' => 28, 'urgent' => 7], fn ($p) => Priority::from($p)),
                'created_at' => $enquiry->created_at,
                'updated_at' => $enquiry->created_at,
            ]);
        }

        // Some completed history so "completed today" isn't empty.
        Task::where('due_at', '<', $this->now->copy()->subDays(2))->inRandomOrder()->limit(8)->update(['completed_at' => $this->now->copy()->subDay()]);

        // Guarantee a few tasks due today for the demo consultant.
        $achieng = $this->users['consultant@wanderlink.test'];
        foreach (Enquiry::open()->where('assigned_to', $achieng->id)->with('customer')->limit(3)->get() as $i => $enquiry) {
            Task::create([
                'customer_id' => $enquiry->customer_id,
                'enquiry_id' => $enquiry->id,
                'assigned_to' => $achieng->id,
                'created_by' => $achieng->id,
                'type' => TaskType::FollowUp,
                'title' => ["Call {$enquiry->customer->first_name} about {$enquiry->destination} quote", "Confirm room types for {$enquiry->destination}", "Chase deposit — {$enquiry->customer->display_name}"][$i],
                'due_at' => $this->now->copy()->setTime(10 + $i * 2, 30),
                'priority' => [Priority::High, Priority::Normal, Priority::Urgent][$i],
            ]);
        }

        app(TaskAutomationService::class)->run();
    }

    private function seedLifecycle(): void
    {
        // Leads with a live quote are prospects.
        Customer::where('lifecycle_stage', LifecycleStage::Lead)
            ->whereHas('enquiries', fn ($q) => $q->whereIn('status', [EnquiryStatus::Quoted, EnquiryStatus::Negotiating]))
            ->update(['lifecycle_stage' => LifecycleStage::Prospect]);

        $service = app(LifecycleService::class);
        foreach (Customer::all() as $customer) {
            $service->evaluate($customer);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Marketing                                                          */
    /* ------------------------------------------------------------------ */

    private function seedMarketing(): void
    {
        $zawadi = $this->users['marketing@wanderlink.test'];
        $segments = collect([
            ['Safari & adventure travellers', 'Past customers who love the bush and the mountains.', ['lifecycle_stage' => ['customer', 'repeat', 'vip'], 'travel_style' => ['safari', 'adventure']]],
            ['Lapsed travellers (12m+)', 'Have travelled before but not in the last year.', ['not_travelled_within_months' => 12]],
            ['Beach & honeymoon dreamers', 'Beach and honeymoon travel styles.', ['travel_style' => ['beach', 'honeymoon']]],
            ['Corporate accounts', 'Companies we handle business travel for.', ['type' => ['corporate']]],
            ['VIP circle', 'Our most valuable customers.', ['lifecycle_stage' => ['vip', 'repeat'], 'min_lifetime_value' => 400000]],
            ['Birthdays this month', 'Send a birthday travel voucher.', ['birthday_month' => (int) $this->now->month]],
        ])->mapWithKeys(fn ($s) => [$s[0] => Segment::create(['name' => $s[0], 'description' => $s[1], 'rules' => $s[2], 'created_by' => $zawadi->id])]);

        $campaigns = [
            ['Great Migration 2026', 'Safari & adventure travellers', CampaignChannel::Email, 'The herds are coming, {{first_name}} 🦓', "Hi {{first_name}},\n\nThe Great Migration reaches the Mara in July. As a returning traveller you get early access to our best camps.\n\nReply to this email or WhatsApp {{consultant_name}} to hold your dates.", 15000, 150],
            ['Easter coast escape', 'Beach & honeymoon dreamers', CampaignChannel::WhatsApp, null, 'Hi {{first_name}}! Easter by the ocean? 4 nights Diani or Zanzibar from KES 48,000pp. Reply YES and {{consultant_name}} will send options. 🌴', 8000, 185],
            ['We miss you — 10% off', 'Lapsed travellers (12m+)', CampaignChannel::Sms, null, 'Hi {{first_name}}, it has been a while since {{last_destination}}! Enjoy 10% off your next WanderLink trip this quarter. Reply STOP to opt out.', 4000, 95],
            ['Corporate travel desk launch', 'Corporate accounts', CampaignChannel::Email, 'A dedicated travel desk for your team', "Dear {{first_name}},\n\nWe've launched a 24/7 corporate travel desk with consolidated monthly invoicing.\n\nBook a 15-minute call with {{consultant_name}}.", 12000, 60],
            ['December holidays early bird', 'VIP circle', CampaignChannel::Email, '{{first_name}}, your December is waiting', "Hi {{first_name}},\n\nAs one of our VIPs, you get first pick of December departures. Loved {{last_destination}}? We have something even better in mind.", 10000, null],
        ];

        $service = app(CampaignService::class);

        foreach ($campaigns as [$name, $segment, $channel, $subject, $body, $cost, $daysAgo]) {
            $campaign = Campaign::create([
                'name' => $name,
                'segment_id' => $segments[$segment]->id,
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'status' => CampaignStatus::Draft,
                'cost' => $cost,
                'created_by' => $zawadi->id,
                'created_at' => $this->now->copy()->subDays(($daysAgo ?? 3) + 5),
            ]);

            if ($daysAgo === null) {
                continue;
            }

            Auth::setUser($zawadi);
            $service->send($campaign);
            $sentAt = $this->now->copy()->subDays($daysAgo)->setTime(9, 0);
            $campaign->update(['sent_at' => $sentAt]);
            Interaction::where('related_type', Campaign::class)->where('related_id', $campaign->id)->update(['occurred_at' => $sentAt]);
            $campaign->recipients()->newPivotStatement()->where('campaign_id', $campaign->id)->whereNotNull('opened_at')
                ->update(['opened_at' => $sentAt->copy()->addHours(5)]);

            // Attribute bookings made by recipients within 60 days of the send.
            $recipientIds = $campaign->recipients()->pluck('customers.id');
            $attributed = Booking::whereIn('customer_id', $recipientIds)->whereNull('campaign_id')
                ->whereBetween('created_at', [$sentAt, $sentAt->copy()->addDays(60)])->get();
            $attributed->each->update(['campaign_id' => $campaign->id]);
            $campaign->update(['conversions' => $attributed->count()]);
        }

        Auth::logout();
    }

    private function seedReportsAndViews(): void
    {
        ScheduledReport::create(['name' => 'Weekly sales summary', 'report_type' => ReportType::SalesSummary, 'frequency' => ReportFrequency::Weekly, 'recipients' => ['owner@wanderlink.test', 'manager@wanderlink.test'], 'created_by' => $this->users['owner@wanderlink.test']->id, 'last_run_at' => $this->now->copy()->subDays(8)]);
        ScheduledReport::create(['name' => 'Daily SLA watch', 'report_type' => ReportType::ServiceLevels, 'frequency' => ReportFrequency::Daily, 'recipients' => ['support@wanderlink.test', 'manager@wanderlink.test'], 'created_by' => $this->users['manager@wanderlink.test']->id, 'last_run_at' => $this->now->copy()->subHours(20)]);
        ScheduledReport::create(['name' => 'Monthly customer growth', 'report_type' => ReportType::CustomerGrowth, 'frequency' => ReportFrequency::Monthly, 'recipients' => ['marketing@wanderlink.test', 'owner@wanderlink.test'], 'created_by' => $this->users['marketing@wanderlink.test']->id]);
        ScheduledReport::create(['name' => 'Pipeline snapshot', 'report_type' => ReportType::Pipeline, 'frequency' => ReportFrequency::Weekly, 'recipients' => ['manager@wanderlink.test'], 'created_by' => $this->users['manager@wanderlink.test']->id, 'last_run_at' => $this->now->copy()->subDays(2)]);

        $achieng = $this->users['consultant@wanderlink.test'];
        SavedView::create(['user_id' => $achieng->id, 'name' => 'My VIP & repeat', 'context' => 'customers', 'filters' => ['stage' => ['vip', 'repeat'], 'consultant' => $achieng->id]]);
        SavedView::create(['user_id' => $achieng->id, 'name' => 'WhatsApp leads', 'context' => 'customers', 'filters' => ['stage' => ['lead', 'prospect'], 'source' => ['whatsapp']]]);
        SavedView::create(['user_id' => $this->users['manager@wanderlink.test']->id, 'name' => 'International customers', 'context' => 'customers', 'filters' => ['country' => ['Australia', 'United Kingdom', 'Germany', 'India', 'United Arab Emirates']]]);
    }

    /** A few real audited changes so the accountability screen has content. */
    private function seedAuditTrail(): void
    {
        $pipeline = app(\App\Services\PipelineService::class);

        Auth::setUser($this->users['consultant@wanderlink.test']);
        foreach (Enquiry::where('assigned_to', $this->users['consultant@wanderlink.test']->id)->where('status', EnquiryStatus::Contacted)->limit(2)->get() as $enquiry) {
            $pipeline->move($enquiry, EnquiryStatus::Quoted);
        }

        Auth::setUser($this->users['manager@wanderlink.test']);
        $customer = Customer::where('assigned_to', $this->users['mercy@wanderlink.test']->id)->first();
        $customer?->update(['assigned_to' => $this->users['brian@wanderlink.test']->id]);
        Enquiry::where('status', EnquiryStatus::New)->first()?->update(['assigned_to' => $this->users['kevin@wanderlink.test']->id]);

        Auth::setUser($this->users['support@wanderlink.test']);
        ServiceTicket::where('status', TicketStatus::Open)->skip(1)->first()?->update(['status' => TicketStatus::InProgress]);

        Auth::setUser($this->users['owner@wanderlink.test']);
        Supplier::where('name', 'Emirates')->first()?->update(['commission_rate' => 7.5]);

        \Spatie\Activitylog\Models\Activity::query()->orderBy('id')->get()->each(function ($activity, $i) {
            $activity->forceFill(['created_at' => $this->now->copy()->subHours(30 - $i * 3), 'updated_at' => $this->now->copy()->subHours(30 - $i * 3)])->save();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function interaction(Customer $customer, InteractionType $type, Direction $direction, string $subject, ?string $body, Carbon $at, $related = null, ?User $user = null): void
    {
        $customer->interactions()->create([
            'user_id' => $user?->id ?? $customer->assigned_to,
            'type' => $type,
            'direction' => $direction,
            'subject' => $subject,
            'body' => $body,
            'occurred_at' => $at,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function pickDestination(Customer $customer): string
    {
        $preferred = $customer->preference?->preferred_destinations ?? [];
        if ($preferred && mt_rand(1, 100) <= 35) {
            return $this->faker->randomElement($preferred);
        }
        if ($customer->type === CustomerType::Corporate && mt_rand(1, 100) <= 60) {
            return $this->faker->randomElement(['London', 'Johannesburg', 'Dubai', 'Cape Town']);
        }

        return $this->weighted(self::DESTINATION_WEIGHTS, fn ($d) => $d);
    }

    /** @return array{0:int, 1:int} adults, children */
    private function travellersFor(Customer $customer): array
    {
        return match ($customer->type) {
            CustomerType::Group => [mt_rand(8, 18), mt_rand(0, 4)],
            CustomerType::Corporate => [mt_rand(1, 3), 0],
            default => $customer->preference?->travel_style === TravelStyle::Family
                ? [2, mt_rand(1, 3)]
                : [$this->weighted([1 => 28, 2 => 58, 3 => 8, 4 => 6], fn ($n) => (int) $n), mt_rand(1, 100) <= 12 ? mt_rand(1, 2) : 0],
        };
    }

    private array $used = [];

    private function uniqueFrom(array $options, string $key): string
    {
        $available = array_values(array_diff($options, $this->used[$key] ?? []));
        $pick = $available ? $this->faker->randomElement($available) : $this->faker->randomElement($options).' '.mt_rand(2, 9);
        $this->used[$key][] = $pick;

        return $pick;
    }

    /**
     * @template T
     *
     * @param  array<string|int, int>  $weights
     * @param  callable(string|int): T  $map
     * @return T
     */
    private function weighted(array $weights, callable $map): mixed
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $map($key);
            }
        }

        return $map(array_key_first($weights));
    }
}
