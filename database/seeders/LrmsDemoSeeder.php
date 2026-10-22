<?php

namespace Database\Seeders;

use App\Models\CaseCategory;
use App\Models\FileMovement;
use App\Models\ImportRun;
use App\Models\LegalFile;
use App\Models\MovementBatch;
use App\Models\Position;
use App\Models\Rack;
use App\Models\Shelf;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LrmsDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    private Carbon $today;

    /** @var array<string, Position> */
    private array $positions = [];

    /** @var array<string, Staff> */
    private array $staff = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, CaseCategory> */
    private array $categories = [];

    /** @var array<string, Shelf> */
    private array $shelves = [];

    /** @var array<string, LegalFile> */
    private array $files = [];

    /** @var array<int, string> */
    private array $activeReferences = [];

    /** @var array<int, string> */
    private array $storedReferences = [];

    public function run(): void
    {
        $this->today = Carbon::create(2026, 9, 12, 10, 0, 0, config('app.timezone'));

        $this->resetDemoData();
        $this->seedPositions();
        $this->seedCategories();
        $this->seedStaffAndUsers();
        $this->seedStorage();
        $this->seedFiles();
        $this->seedMovements();
        $this->seedImportRuns();
    }

    private function resetDemoData(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('import_runs')->truncate();
        DB::table('file_movements')->truncate();
        DB::table('movement_batches')->truncate();
        DB::table('legal_file_links')->truncate();
        DB::table('legal_files')->truncate();
        DB::table('system_sequences')->truncate();
        DB::table('shelves')->truncate();
        DB::table('racks')->truncate();
        DB::table('case_categories')->truncate();
        DB::table('users')->where('role', User::ROLE_USER)->delete();
        DB::table('staff')->truncate();
        DB::table('permission_position')->truncate();
        DB::table('positions')->truncate();

        Schema::enableForeignKeyConstraints();
    }

    private function seedCategories(): void
    {
        $definitions = [
            ['code' => 'CON', 'name' => 'Conveyancing'],
            ['code' => 'LOA', 'name' => 'Loan Documentation'],
            ['code' => 'TEN', 'name' => 'Tenancy'],
            ['code' => 'COM', 'name' => 'Commercial'],
            ['code' => 'EST', 'name' => 'Estate / Probate'],
            ['code' => 'LIT', 'name' => 'Litigation'],
            ['code' => 'WIL', 'name' => 'Will'],
        ];

        foreach ($definitions as $definition) {
            $this->categories[$definition['code']] = CaseCategory::query()->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'is_active' => true,
            ]);
        }
    }

    private function seedPositions(): void
    {
        $definitions = [
            [
                'name' => 'Records & Registry Manager',
                'slug' => 'records-registry-manager',
                'description' => 'Leads the physical file registry, borrowing controls and audit reporting.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'staff.view', 'staff.create', 'staff.update', 'staff.deactivate', 'staff.qr_print',
                    'storage.view', 'storage.manage', 'categories.view', 'categories.manage', 'files.view', 'files.create', 'files.update', 'files.delete',
                    'completions.request', 'completions.review',
                    'labels.print', 'movements.borrow', 'movements.pic_intake', 'movements.pic_return_receive', 'movements.history', 'missing.manage',
                    'imports.view', 'imports.create', 'exports.files', 'exports.movements', 'audit.view',
                ],
            ],
            [
                'name' => 'Senior Conveyancing Executive',
                'slug' => 'senior-conveyancing-executive',
                'description' => 'Handles subsale, loan and perfection matters for residential and commercial property.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'files.update', 'completions.request', 'labels.print',
                    'movements.borrow', 'movements.history',
                ],
            ],
            [
                'name' => 'Conveyancing Clerk',
                'slug' => 'conveyancing-clerk',
                'description' => 'Maintains file movement readiness, completions and banking documents.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'labels.print',
                    'movements.borrow', 'movements.history',
                ],
            ],
            [
                'name' => 'Corporate Legal Executive',
                'slug' => 'corporate-legal-executive',
                'description' => 'Supports corporate transactions, shareholder documentation and board approvals.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'files.update', 'completions.request', 'labels.print', 'movements.history',
                ],
            ],
            [
                'name' => 'Litigation Secretary',
                'slug' => 'litigation-secretary',
                'description' => 'Coordinates litigation bundles, court papers and service copies.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'labels.print', 'movements.borrow', 'movements.history',
                ],
            ],
            [
                'name' => 'Finance & Administration Executive',
                'slug' => 'finance-administration-executive',
                'description' => 'Maintains payment records, office administration and approval support.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'movements.history', 'imports.view', 'exports.files',
                ],
            ],
            [
                'name' => 'Reception & Document Control Officer',
                'slug' => 'reception-document-control-officer',
                'description' => 'Manages front-desk intake, registry dispatch and daily file requests.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'labels.print',
                ],
            ],
            [
                'name' => 'Trainee Solicitor',
                'slug' => 'trainee-solicitor',
                'description' => 'Reviews transactional documents and attends to supervised file work.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'movements.borrow', 'movements.history',
                ],
            ],
            [
                'name' => 'Records Officer',
                'slug' => 'records-officer',
                'description' => 'Performs daily filing, shelving, retrieval and discrepancy checks.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'staff.view', 'staff.qr_print', 'storage.view', 'categories.view', 'files.view',
                    'labels.print', 'movements.borrow', 'movements.pic_intake', 'movements.pic_return_receive', 'movements.history', 'missing.manage',
                ],
            ],
            [
                'name' => 'Office Services Assistant',
                'slug' => 'office-services-assistant',
                'description' => 'Supports registry runs, courier dispatches and off-site storage requests.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'movements.borrow',
                ],
            ],
            [
                'name' => 'Temporary Filing Assistant',
                'slug' => 'temporary-filing-assistant',
                'description' => 'Used for ad-hoc registry support during backlog clearance.',
                'is_active' => false,
                'permissions' => [
                    'files.view',
                ],
            ],
        ];

        $permissions = DB::table('permissions')->pluck('id', 'name');

        foreach ($definitions as $definition) {
            $position = Position::query()->create([
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $definition['description'],
                'is_active' => $definition['is_active'],
            ]);

            $position->permissions()->sync(
                collect($definition['permissions'])
                    ->map(fn (string $permission) => $permissions[$permission] ?? null)
                    ->filter()
                    ->all(),
            );

            $this->positions[$definition['slug']] = $position;
        }
    }

    private function seedStaffAndUsers(): void
    {
        $primaryAdmin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_APPROVED,
                'reviewed_at' => $this->today->copy()->subMonths(6),
            ],
        );

        $adminUsers = [
            $primaryAdmin,
            User::query()->updateOrCreate(
                ['email' => 'faridah.othman@tsangco.com.my'],
                [
                    'name' => 'Faridah Othman',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => User::ROLE_ADMIN,
                    'status' => User::STATUS_APPROVED,
                    'reviewed_at' => $this->today->copy()->subMonths(5),
                ],
            ),
            User::query()->updateOrCreate(
                ['email' => 'kelvin.low@tsangco.com.my'],
                [
                    'name' => 'Kelvin Low',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => User::ROLE_ADMIN,
                    'status' => User::STATUS_APPROVED,
                    'reviewed_at' => $this->today->copy()->subMonths(5),
                ],
            ),
        ];

        foreach ($adminUsers as $adminUser) {
            $this->users[$adminUser->email] = $adminUser;
        }

        $approved = [
            ['TC16014', 'Aisyah Karim', 'aisyah.karim@tsangco.com.my', '012-218 4301', 'records-registry-manager'],
            ['TC17008', 'Muhammad Firdaus Rahman', 'firdaus.rahman@tsangco.com.my', '012-323 8045', 'senior-conveyancing-executive'],
            ['TC18022', 'Nurul Izzati Jasni', 'nurul.izzati@tsangco.com.my', '014-771 2984', 'conveyancing-clerk'],
            ['TC18041', 'Chan Mei Ling', 'meiling.chan@tsangco.com.my', '016-239 4152', 'corporate-legal-executive'],
            ['TC19006', 'Lim Wei Jian', 'weijian.lim@tsangco.com.my', '012-881 4330', 'litigation-secretary'],
            ['TC19027', 'Siti Hajar Mokhtar', 'siti.hajar@tsangco.com.my', '013-620 8421', 'finance-administration-executive'],
            ['TC20011', 'Adib Hakim Roslan', 'adib.hakim@tsangco.com.my', '017-299 5504', 'reception-document-control-officer'],
            ['TC20024', 'Farah Nadia Shukri', 'farah.nadia@tsangco.com.my', '019-306 4822', 'trainee-solicitor'],
            ['TC20035', 'Tan Jia Wen', 'jiawen.tan@tsangco.com.my', '012-366 1942', 'records-officer'],
            ['TC21009', 'Nur Amalina Salleh', 'amalina.salleh@tsangco.com.my', '018-248 7613', 'conveyancing-clerk'],
            ['TC21018', 'Ong Kok Leong', 'kokleong.ong@tsangco.com.my', '016-907 2854', 'senior-conveyancing-executive'],
            ['TC21031', 'Priya Menon', 'priya.menon@tsangco.com.my', '017-664 3291', 'corporate-legal-executive'],
            ['TC22007', 'Muhammad Danish Iskandar', 'danish.iskandar@tsangco.com.my', '013-853 1187', 'records-officer'],
            ['TC22016', 'Lee Su Yin', 'suyin.lee@tsangco.com.my', '014-225 6339', 'litigation-secretary'],
            ['TC22028', 'Sharmila Devi Rajan', 'sharmila.rajan@tsangco.com.my', '012-700 4125', 'finance-administration-executive'],
            ['TC22034', 'Hafizuddin Salleh', 'hafizuddin.salleh@tsangco.com.my', '011-217 4680', 'office-services-assistant'],
            ['TC23005', 'Nur Qistina Azhar', 'qistina.azhar@tsangco.com.my', '017-480 1932', 'conveyancing-clerk'],
            ['TC23017', 'Goh Xin Yi', 'xinyi.goh@tsangco.com.my', '016-283 7045', 'trainee-solicitor'],
            ['TC23029', 'Zarul Fahmi Baharuddin', 'zarul.fahmi@tsangco.com.my', '012-947 2351', 'records-officer'],
            ['TC23040', 'Wong Pei Shan', 'peishan.wong@tsangco.com.my', '018-501 7736', 'corporate-legal-executive'],
            ['TC24003', 'Nursyafiqah Harun', 'syafiqah.harun@tsangco.com.my', '014-934 6008', 'conveyancing-clerk'],
            ['TC24014', 'Kishen Raj Kumar', 'kishen.kumar@tsangco.com.my', '016-489 1627', 'reception-document-control-officer'],
            ['TC24026', 'Aina Sofea Mazlan', 'aina.sofea@tsangco.com.my', '012-630 9056', 'litigation-secretary'],
            ['TC24038', 'Cheah Yi Xuan', 'yixuan.cheah@tsangco.com.my', '017-871 2240', 'records-officer'],
        ];

        foreach ($approved as [$staffNumber, $name, $email, $phone, $positionSlug]) {
            $staff = $this->createStaff($staffNumber, $name, $email, $phone, $positionSlug, true);
            $reviewer = $staffNumber < 'TC22000' ? 'faridah.othman@tsangco.com.my' : 'kelvin.low@tsangco.com.my';
            $reviewedAt = $this->today->copy()->subDays((int) substr($staffNumber, -2) + 20);
            $user = User::query()->create([
                'staff_id' => $staff->id,
                'requested_position_id' => $this->positions[$positionSlug]->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_APPROVED,
                'reviewed_at' => $reviewedAt,
                'reviewed_by' => $this->users[$reviewer]->id,
            ]);
            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $reviewedAt->copy()->subDays(8),
                'updated_at' => $reviewedAt,
            ]);
            $this->users[$email] = $user;
        }

        $pending = [
            ['TC24041', 'Nur Afiqah Kamal', 'afiqah.kamal@tsangco.com.my', '014-275 5106', 'conveyancing-clerk'],
            ['TC24042', 'Jason Teoh Wen Jun', 'jason.teoh@tsangco.com.my', '016-476 3801', 'records-officer'],
            ['TC24043', 'Sabrina Natasya Osman', 'sabrina.osman@tsangco.com.my', '018-763 2044', 'corporate-legal-executive'],
            ['TC24044', 'Muhammad Ariff Haikal', 'ariff.haikal@tsangco.com.my', '012-716 5910', 'conveyancing-clerk'],
            ['TC24045', 'Sharifah Alya Husna', 'alya.husna@tsangco.com.my', '017-235 6450', 'litigation-secretary'],
            ['TC24046', 'Teh Xin Rou', 'xinrou.teh@tsangco.com.my', '016-350 2806', 'records-officer'],
            ['TC24047', 'Nurin Balqis Rosman', 'nurin.balqis@tsangco.com.my', '014-613 4708', 'trainee-solicitor'],
            ['TC24048', 'Mohamad Syakir Amin', 'syakir.amin@tsangco.com.my', '013-745 6281', 'reception-document-control-officer'],
            ['TC24049', 'Angeline Lee Hui Wen', 'angeline.lee@tsangco.com.my', '012-909 5144', 'finance-administration-executive'],
            ['TC24050', 'Nur Huda Zaharah', 'huda.zaharah@tsangco.com.my', '019-466 8257', 'corporate-legal-executive'],
            ['TC24051', 'Rajiv Prakash Nair', 'rajiv.nair@tsangco.com.my', '016-818 4320', 'senior-conveyancing-executive'],
            ['TC24052', 'Hanis Iman Abdullah', 'hanis.iman@tsangco.com.my', '017-654 1378', 'conveyancing-clerk'],
            ['TC24053', 'Chong Pei Yee', 'peiyee.chong@tsangco.com.my', '014-290 7784', 'records-officer'],
            ['TC24054', 'Nur Aina Farzana', 'aina.farzana@tsangco.com.my', '011-267 4903', 'trainee-solicitor'],
            ['TC24055', 'Kok Sheng Hao', 'shenghao.kok@tsangco.com.my', '012-640 5219', 'litigation-secretary'],
            ['TC24056', 'Dayang Sofea Jamil', 'dayang.sofea@tsangco.com.my', '013-871 2405', 'conveyancing-clerk'],
            ['TC24057', 'Melissa Anne Daniel', 'melissa.daniel@tsangco.com.my', '018-423 7654', 'corporate-legal-executive'],
            ['TC24058', 'Azran Qayyum Salleh', 'azran.salleh@tsangco.com.my', '016-522 1840', 'records-officer'],
        ];

        foreach ($pending as $index => [$staffNumber, $name, $email, $phone, $requestedSlug]) {
            $staff = $this->createStaff($staffNumber, $name, $email, $phone, null, true);
            $submittedAt = $this->today->copy()->subDays($index + 1);
            $user = User::query()->create([
                'staff_id' => $staff->id,
                'requested_position_id' => $this->positions[$requestedSlug]->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_PENDING,
            ]);
            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
            $this->users[$email] = $user;
        }

        $rejected = [
            ['TC24059', 'Nur Maisarah Anuar', 'maisarah.anuar@tsangco.com.my', '017-519 8042', 'conveyancing-clerk', 'Duplicate supporting documents were submitted with mismatched NRIC details.'],
            ['TC24060', 'Eugene Tan Chee Wai', 'eugene.tan@tsangco.com.my', '016-873 2645', 'records-officer', 'The staff onboarding checklist is still incomplete and the department head has not cleared system access.'],
            ['TC24061', 'Athirah Humaira Rosdi', 'athirah.rosdi@tsangco.com.my', '014-922 3186', 'trainee-solicitor', 'The requested position does not match the confirmed intake record from Human Resources.'],
            ['TC24062', 'Vignesh Kumaravel', 'vignesh.kumaravel@tsangco.com.my', '012-377 6801', 'litigation-secretary', 'Employment start date has been deferred, so the account request must be resubmitted nearer to reporting date.'],
            ['TC24063', 'Serene Choong Yi Ling', 'serene.choong@tsangco.com.my', '018-308 9274', 'corporate-legal-executive', 'Line manager requested a corrected company email before access can be approved.'],
        ];

        foreach ($rejected as $index => [$staffNumber, $name, $email, $phone, $requestedSlug, $reason]) {
            $staff = $this->createStaff($staffNumber, $name, $email, $phone, null, true);
            $submittedAt = $this->today->copy()->subDays($index + 12);
            $reviewedAt = $this->today->copy()->subDays($index + 6);
            $user = User::query()->create([
                'staff_id' => $staff->id,
                'requested_position_id' => $this->positions[$requestedSlug]->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_REJECTED,
                'reviewed_at' => $reviewedAt,
                'reviewed_by' => $this->users['faridah.othman@tsangco.com.my']->id,
                'rejection_reason' => $reason,
            ]);
            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $submittedAt,
                'updated_at' => $reviewedAt,
            ]);
            $this->users[$email] = $user;
        }

        $staffWithoutAccounts = [
            ['TC18055', 'Roslina Binti Mahmud', 'roslina.mahmud@tsangco.com.my', '012-614 8305', 'office-services-assistant', true],
            ['TC19044', 'Cheong Kai Sheng', 'kaisheng.cheong@tsangco.com.my', '019-331 5477', 'records-officer', true],
            ['TC20052', 'Mastura Abdul Halim', 'mastura.halim@tsangco.com.my', '014-862 2498', 'reception-document-control-officer', true],
            ['TC21047', 'Suresh Kumar Nadarajan', 'suresh.nadarajan@tsangco.com.my', '016-544 7109', 'office-services-assistant', true],
            ['TC22049', 'Vivian Liew Sze Ern', 'vivian.liew@tsangco.com.my', '018-721 3358', 'records-officer', true],
            ['TC23051', 'Khairul Anuar Basri', 'khairul.basri@tsangco.com.my', '012-995 1626', 'office-services-assistant', true],
            ['TC24064', 'Nur Adriana Ismail', 'adriana.ismail@tsangco.com.my', '014-611 2840', 'finance-administration-executive', true],
            ['TC24065', 'Jeffrey Ng Chun Kit', 'jeffrey.ng@tsangco.com.my', '017-521 8063', 'records-officer', true],
            ['TC17033', 'Puan Sri Devi Narayan', 'devi.narayan@tsangco.com.my', '016-671 2098', 'temporary-filing-assistant', false],
            ['TC18062', 'Mohd Faizal Rahmat', 'faizal.rahmat@tsangco.com.my', '013-900 7615', 'office-services-assistant', false],
        ];

        foreach ($staffWithoutAccounts as [$staffNumber, $name, $email, $phone, $positionSlug, $active]) {
            $this->createStaff($staffNumber, $name, $email, $phone, $positionSlug, $active);
        }
    }

    private function createStaff(
        string $staffNumber,
        string $name,
        string $email,
        string $phone,
        ?string $positionSlug,
        bool $isActive,
    ): Staff {
        $staff = new Staff;
        $staff->staff_number = $staffNumber;
        $staff->full_name = $name;
        $staff->email = $email;
        $staff->phone = $phone;
        $staff->position_id = $positionSlug ? $this->positions[$positionSlug]->id : null;
        $staff->is_active = $isActive;
        $staff->qr_identifier = (string) Str::uuid();
        $staff->save();

        $this->staff[$staffNumber] = $staff;

        return $staff;
    }

    private function seedStorage(): void
    {
        $definitions = [
            'A1' => ['name' => 'Residential Conveyancing Bay', 'shelves' => ['S1' => 'Shelf Alpha', 'S2' => 'Shelf Bravo', 'S3' => 'Shelf Charlie']],
            'A2' => ['name' => 'Loan Documentation Bay', 'shelves' => ['S1' => 'Shelf Delta', 'S2' => 'Shelf Echo', 'S3' => 'Shelf Foxtrot']],
            'A3' => ['name' => 'Perfection Matters Bay', 'shelves' => ['S1' => 'Shelf Golf', 'S2' => 'Shelf Hotel', 'S3' => 'Shelf India']],
            'B1' => ['name' => 'Title Deed Vault', 'shelves' => ['S1' => 'Shelf Jade', 'S2' => 'Shelf Kappa', 'S3' => 'Shelf Lima']],
            'B2' => ['name' => 'Commercial Matters Vault', 'shelves' => ['S1' => 'Shelf Meranti', 'S2' => 'Shelf Nusa', 'S3' => 'Shelf Omega']],
            'B3' => ['name' => 'Probate & Estate Vault', 'shelves' => ['S1' => 'Shelf Pertiwi', 'S2' => 'Shelf Quartz', 'S3' => 'Shelf Rimba']],
            'C1' => ['name' => 'Archived Residential Files', 'shelves' => ['S1' => 'Shelf Selatan', 'S2' => 'Shelf Timur', 'S3' => 'Shelf Utara']],
            'C2' => ['name' => 'Archived Corporate Files', 'shelves' => ['S1' => 'Shelf Vanda', 'S2' => 'Shelf Wira', 'S3' => 'Shelf Xenia']],
            'C3' => ['name' => 'Off-Site Transfer Staging', 'shelves' => ['S1' => 'Shelf Yakin', 'S2' => 'Shelf Zamrud', 'S3' => 'Shelf Angsana']],
            'J1' => ['name' => 'Johor Residential Holdings', 'shelves' => ['S1' => 'Shelf Bakawali', 'S2' => 'Shelf Cempaka', 'S3' => 'Shelf Dahlia']],
            'J2' => ['name' => 'Southern Commercial Holdings', 'shelves' => ['S1' => 'Shelf Enggang', 'S2' => 'Shelf Fajar', 'S3' => 'Shelf Gemilang']],
        ];

        foreach ($definitions as $rackCode => $rackDefinition) {
            $rack = Rack::query()->create([
                'code' => $rackCode,
                'name' => $rackDefinition['name'],
                'is_active' => true,
            ]);

            foreach ($rackDefinition['shelves'] as $shelfCode => $shelfName) {
                $shelf = Shelf::query()->create([
                    'rack_id' => $rack->id,
                    'code' => $shelfCode,
                    'name' => $shelfName,
                    'is_active' => true,
                ]);

                $this->shelves["{$rackCode}|{$shelfCode}"] = $shelf;
            }
        }
    }

    private function seedFiles(): void
    {
        $matterBlueprints = [
            ['prefix' => 'CON', 'matter' => 'Subsale SPA'],
            ['prefix' => 'LOA', 'matter' => 'Loan Documentation'],
            ['prefix' => 'CON', 'matter' => 'Perfection of Transfer'],
            ['prefix' => 'LOA', 'matter' => 'Perfection of Charge'],
            ['prefix' => 'CON', 'matter' => 'Discharge of Charge'],
            ['prefix' => 'TEN', 'matter' => 'Tenancy Agreement'],
            ['prefix' => 'COM', 'matter' => 'Commercial Lease'],
            ['prefix' => 'EST', 'matter' => 'Probate & Transmission'],
        ];

        $individualPurchasers = [
            'Amirul Hakim Hashim', 'Nur Aina Syazwani', 'Kevin Tan Chee Hao', 'Puteri Sofea Idris',
            'Muhammad Alif Firman', 'Chloe Ng Sze Ting', 'Nurul Athirah Kamarudin', 'Rohit Menon',
            'Lim Jia Hui', 'Haziq Azman', 'Kok Zhen Rui', 'Liyana Irdina Jamaluddin',
            'Tharshini Subramaniam', 'Yap Kai Wen', 'Nur Adlina Rahim', 'Jonathan Goh Wei Seng',
            'Nadia Syuhada Ahmad', 'Evelyn Chia Shu Wen', 'Mohamad Fikri Yaacob', 'Melissa Foo Pei Ling',
            'Sharvin Rajendran', 'Nurin Izzara Musa', 'Jason Chua Tze Ming', 'Syafiqah Nabilah Omar',
        ];

        $corporatePurchasers = [
            'Bayu Sentosa Holdings Sdn Bhd', 'Rimbun Vista Assets Sdn Bhd', 'Setia Murni Advisory Sdn Bhd',
            'Nusantara Prime Ventures Sdn Bhd', 'Meridian Crest Developments Sdn Bhd', 'Helang Maju Properties Sdn Bhd',
            'Tegas Land Capital Sdn Bhd', 'Saujana Equity Partners Sdn Bhd', 'Wawasan Alam Realty Sdn Bhd',
            'Cemerlang Metro Holdings Sdn Bhd', 'Jaya Sentral Ventures Sdn Bhd', 'Eastshore Commerce Sdn Bhd',
        ];

        $vendors = [
            'Seri Damai Development Sdn Bhd', 'Mahkota Hartanah Sdn Bhd', 'Tropika Meridian Sdn Bhd',
            'Puncak Idaman Resources Sdn Bhd', 'Kencana Sri Properties Sdn Bhd', 'Noble Legacy Land Sdn Bhd',
            'Sri Kenari Construction Sdn Bhd', 'Bandar Wawasan Development Sdn Bhd', 'The estate of Lim Kok Chye',
            'The estate of Salmah Binti Ahmad', 'Taman Bukit Bayu Sdn Bhd', 'Aurora City Holdings Sdn Bhd',
            'The estate of Gurcharan Singh', 'Duta Seri Capital Sdn Bhd', 'Mutiara Heights Development Sdn Bhd',
            'Southbank Residence Sdn Bhd', 'Pelangi Vista Properties Sdn Bhd', 'Orkid Aman Sdn Bhd',
        ];

        $developments = [
            ['Residensi Sfera Damansara', 'Jalan PJU 10/15A, Damansara Damai, Selangor'],
            ['Mutiara Sentul Condominium', 'Jalan Sentul Pasar, Kuala Lumpur'],
            ["D'Pristine Medini", 'Persiaran Medini Utara 1, Iskandar Puteri, Johor'],
            ['Setia Eco Glades', 'Cybersouth, Dengkil, Selangor'],
            ['The Greens @ Subang West', 'Persiaran Subang Permai, Shah Alam, Selangor'],
            ['Tropicana Aman', 'Jalan Aman Suria, Kota Kemuning, Selangor'],
            ['Bayu Marina Resort Residences', 'Jalan Bayu Puteri 2, Johor Bahru, Johor'],
            ['Seri Tanjung Pinang', 'Lebuh Tanjung Tokong, Penang'],
            ['Alam Impian', 'Persiaran Sukan, Shah Alam, Selangor'],
            ['Sunway Geo Residences', 'Jalan Lagoon Selatan, Bandar Sunway, Selangor'],
            ['The Birch', 'Jalan Kasipillay, Sentul, Kuala Lumpur'],
            ['Kiara 163 Residency', 'Jalan Kiara 1, Mont Kiara, Kuala Lumpur'],
            ['Bukit Jalil Senja', 'Jalan Jalil Perkasa 3, Bukit Jalil, Kuala Lumpur'],
            ['Aster Hill Terrace', 'Persiaran Aster 6, Sungai Long, Kajang, Selangor'],
            ['Eco Botanic', 'Persiaran Eco Botanic, Iskandar Puteri, Johor'],
            ['Lakefront Homes', 'Jalan Tasik Prima 8, Puchong, Selangor'],
            ['Pine Crest Office Suites', 'Jalan Teknologi 5, Taman Sains Selangor, Kota Damansara'],
            ['Mulia Avenue Shop Offices', 'Jalan SS 2/72, Petaling Jaya, Selangor'],
            ['Taman Bukit Anggerik', 'Jalan Bukit Anggerik 4/2, Cheras, Kuala Lumpur'],
            ['Tropicana Gardens', 'Persiaran Surian, Kota Damansara, Selangor'],
        ];

        $activeShelfKeys = array_keys($this->shelves);
        $personInChargeNumbers = [
            'TC17008', 'TC18022', 'TC18041', 'TC19006', 'TC20024', 'TC21018',
            'TC21031', 'TC22016', 'TC23005', 'TC23040', 'TC24003', 'TC24026',
        ];
        $registrar = $this->users['aisyah.karim@tsangco.com.my'];
        $reviewer = $this->users['faridah.othman@tsangco.com.my'];

        for ($index = 1; $index <= 138; $index++) {
            $blueprint = $matterBlueprints[($index - 1) % count($matterBlueprints)];
            $isCorporate = in_array($blueprint['prefix'], ['COM'], true) || $index % 7 === 0;
            $purchaserPool = $isCorporate ? $corporatePurchasers : $individualPurchasers;
            $development = $developments[($index - 1) % count($developments)];
            $shelfKey = $activeShelfKeys[($index - 1) % count($activeShelfKeys)];
            $category = $this->categories[$blueprint['prefix']];
            $sequenceNumber = $category->reserveNextNumber();
            $referenceNumber = $category->formatReference($sequenceNumber, 2026);
            $createdAt = $index > 128
                ? $this->today->copy()->subDays(138 - $index)->setTime(9 + ($index % 4), ($index * 7) % 60)
                : Carbon::create(2026, 2, 3, 9, 0, 0, config('app.timezone'))->addDays($index);

            $property = $this->propertyDescription($index, $development[0], $development[1], $blueprint['matter']);
            $file = new LegalFile;
            $file->file_identifier = LegalFile::nextIdentifier();
            $file->qr_identifier = (string) Str::uuid();
            $file->reference_number = $referenceNumber;
            $file->purchaser = $purchaserPool[($index - 1) % count($purchaserPool)];
            $file->vendor = $vendors[($index + 2) % count($vendors)];
            $file->property = $property;
            $file->case_category_id = $category->id;
            $file->case_type = match ($blueprint['prefix']) {
                'LOA' => LegalFile::TYPE_LOAN,
                'LIT' => LegalFile::TYPE_LITIGATION,
                default => LegalFile::TYPE_SNP,
            };
            $file->matter_type = $category->name;
            $file->start_date = $createdAt->toDateString();
            $file->end_date = $createdAt->copy()->addDays(30 + ($index % 45))->toDateString();
            $file->person_in_charge_id = $this->staff[$personInChargeNumbers[($index - 1) % count($personInChargeNumbers)]]->id;
            $file->shelf_id = null;
            $file->status = LegalFile::STATUS_REGISTERED;
            $file->created_by = $registrar->id;
            $file->updated_by = $registrar->id;
            $file->save();

            DB::table('legal_files')->where('id', $file->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $file->refresh();
            $this->files[$referenceNumber] = $file;
            $this->activeReferences[] = $referenceNumber;

            $this->recordLifecycleEvent(
                $file,
                'registered',
                null,
                LegalFile::STATUS_REGISTERED,
                $registrar,
                $createdAt,
                'Case registered in LRMS.',
            );

            // 1-10 remain Registered (awaiting completion).
            if ($index <= 10) {
                continue;
            }

            $requestedAt = $createdAt->copy()->addDays(4 + ($index % 3))->setTime(11, 15);
            $this->recordLifecycleEvent(
                $file,
                'completion_requested',
                LegalFile::STATUS_REGISTERED,
                LegalFile::STATUS_PENDING,
                $registrar,
                $requestedAt,
                'Matter finished. Submitted for boss review.',
            );
            $this->updateFileState($file, LegalFile::STATUS_PENDING, null, $registrar, $requestedAt);

            // 11-16 remain Pending (Completion queue).
            if ($index <= 16) {
                continue;
            }

            $approvedAt = $requestedAt->copy()->addDays(1 + ($index % 2))->setTime(15, 40);
            $this->recordLifecycleEvent(
                $file,
                'completion_approved',
                LegalFile::STATUS_PENDING,
                LegalFile::STATUS_COMPLETED,
                $reviewer,
                $approvedAt,
                'Physical file checked and completion approved.',
            );
            $this->updateFileState($file, LegalFile::STATUS_COMPLETED, null, $reviewer, $approvedAt);

            // 17-22 remain Completed (Store File queue).
            if ($index <= 22) {
                continue;
            }

            $storedAt = $approvedAt->copy()->addHours(18 + ($index % 6));
            $this->storeFile($file, $this->shelves[$shelfKey], $this->staff['TC20035'], $registrar, $storedAt);
            $this->storedReferences[] = $referenceNumber;
        }

        $this->seedRelatedFileLinks();
    }

    private function seedRelatedFileLinks(): void
    {
        $pairs = [
            [$this->storedReferences[0] ?? null, $this->storedReferences[1] ?? null],
            [$this->storedReferences[2] ?? null, $this->storedReferences[3] ?? null],
            [$this->activeReferences[0] ?? null, $this->activeReferences[1] ?? null],
        ];

        foreach ($pairs as [$primaryRef, $relatedRef]) {
            if (! $primaryRef || ! $relatedRef || $primaryRef === $relatedRef) {
                continue;
            }

            $this->files[$primaryRef]->syncRelatedFiles([$this->files[$relatedRef]->id]);
        }
    }

    private function seedMovements(): void
    {
        $processors = [
            $this->users['aisyah.karim@tsangco.com.my'],
            $this->users['faridah.othman@tsangco.com.my'],
            $this->users['kelvin.low@tsangco.com.my'],
        ];

        $borrowers = [
            $this->staff['TC17008'],
            $this->staff['TC18022'],
            $this->staff['TC18041'],
            $this->staff['TC19006'],
            $this->staff['TC20024'],
            $this->staff['TC21018'],
            $this->staff['TC22016'],
            $this->staff['TC23005'],
            $this->staff['TC23040'],
            $this->staff['TC24003'],
            $this->staff['TC24026'],
            $this->staff['TC22049'],
        ];

        $receivers = [
            $this->staff['TC20035'],
            $this->staff['TC22007'],
            $this->staff['TC23029'],
            $this->staff['TC24038'],
        ];

        $references = $this->storedReferences;

        foreach (array_chunk(array_slice($references, 0, 30), 3) as $chunkIndex => $chunk) {
            $borrowedAt = Carbon::create(2026, 3, 3, 10, 15, 0, config('app.timezone'))->addDays($chunkIndex * 4);
            $returnedAt = $borrowedAt->copy()->addDays(7 + ($chunkIndex % 3));
            $employee = $borrowers[$chunkIndex % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $pic = $receivers[$chunkIndex % count($receivers)];

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Borrowed for stamping bundle preparation and client signing appointment.');
            $this->returnFiles($chunk, $employee, $pic, $processor, $returnedAt, 'Returned after execution set and duplicate copies were filed.');
        }

        foreach (array_chunk(array_slice($references, 30, 20), 4) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 2) % count($borrowers)];
            $processor = $processors[($chunkIndex + 1) % count($processors)];
            $pic = $receivers[($chunkIndex + 1) % count($receivers)];
            $firstBorrow = Carbon::create(2026, 4, 18, 9, 20, 0, config('app.timezone'))->addDays($chunkIndex * 5);
            $firstReturn = $firstBorrow->copy()->addDays(6);
            $secondBorrow = $firstReturn->copy()->addDays(10);
            $secondReturn = $secondBorrow->copy()->addDays(5 + ($chunkIndex % 2));

            $this->borrowFiles($chunk, $employee, $processor, $firstBorrow, 'Retrieved for financier execution and solicitor certification.');
            $this->returnFiles($chunk, $employee, $pic, $processor, $firstReturn, 'Refiled after first drawdown documents were completed.');
            $this->borrowFiles($chunk, $employee, $processor, $secondBorrow, 'Borrowed again for perfection follow-up and undertaking review.');
            $this->returnFiles($chunk, $employee, $pic, $processor, $secondReturn, 'Returned after status update letter and lodged forms were copied.');
        }

        foreach (array_chunk(array_slice($references, 50, 10), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 4) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $borrowedAt = Carbon::create(2026, 5, 9, 11, 10, 0, config('app.timezone'))->addDays($chunkIndex * 6);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Borrowed pending long-form completion, bank undertakings and redemption statements.');
        }

        foreach (array_chunk(array_slice($references, 60, 8), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 1) % count($borrowers)];
            $processor = $processors[($chunkIndex + 2) % count($processors)];
            $borrowedAt = Carbon::create(2026, 8, 5, 14, 5, 0, config('app.timezone'))->addDays($chunkIndex * 2);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Borrowed for imminent completion meeting and disbursement verification.');
        }

        foreach (array_chunk(array_slice($references, 68, 6), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 6) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $pic = $receivers[$chunkIndex % count($receivers)];
            $borrowedAt = Carbon::create(2026, 6, 20, 9, 40, 0, config('app.timezone'))->addDays($chunkIndex * 3);
            $returnedAt = $borrowedAt->copy()->addDays(4);
            $missingAt = $returnedAt->copy()->addHours(18);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Borrowed for redemption and requisition reply review.');
            $this->returnFiles($chunk, $employee, $pic, $processor, $returnedAt, 'Returned to shelf after follow-up with land office bundle.');

            foreach ($chunk as $referenceNumber) {
                $this->markMissing(
                    $referenceNumber,
                    $processors[($chunkIndex + 1) % count($processors)],
                    $missingAt->copy()->addMinutes((int) substr($referenceNumber, -1) * 3),
                    'Marked missing during shelf reconciliation after an internal handover check.',
                );
            }
        }

        foreach (array_chunk(array_slice($references, 74, 4), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 3) % count($borrowers)];
            $processor = $processors[($chunkIndex + 1) % count($processors)];
            $pic = $receivers[($chunkIndex + 2) % count($receivers)];
            $borrowedAt = $this->today->copy()->subDay()->setTime(15, 20 + ($chunkIndex * 7));

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Taken out for final client execution before same-day return.');
            $this->returnFiles(
                $chunk,
                $employee,
                $pic,
                $processor,
                $this->today->copy()->setTime(9, 15 + ($chunkIndex * 12)),
                'Returned this morning after client acknowledgment and indexing.',
            );
        }

        foreach (array_chunk(array_slice($references, 78, 6), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 8) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $this->borrowFiles(
                $chunk,
                $employee,
                $processor,
                $this->today->copy()->setTime(10 + $chunkIndex, 10 + ($chunkIndex * 8)),
                'Borrowed today for urgent signing, completion payment advice and bank presentation.',
            );
        }

        foreach (array_chunk(array_slice($references, 84, 18), 3) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 5) % count($borrowers)];
            $processor = $processors[($chunkIndex + 2) % count($processors)];
            $pic = $receivers[($chunkIndex + 3) % count($receivers)];
            $borrowedAt = Carbon::create(2026, 5, 28, 9, 0, 0, config('app.timezone'))->addDays($chunkIndex * 3);
            $returnedAt = $borrowedAt->copy()->addDays(2 + ($chunkIndex % 4));

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, 'Borrowed for certified true copy extraction, execution and requisition handling.');
            $this->returnFiles($chunk, $employee, $pic, $processor, $returnedAt, 'Returned after pending correspondences were uploaded and paper copies were stamped.');
        }

        foreach (array_slice($references, 102, 4) as $index => $referenceNumber) {
            $processor = $processors[$index % count($processors)];
            $missingAt = Carbon::create(2026, 6, 15, 16, 20, 0, config('app.timezone'))->addDays($index);
            $foundAt = $missingAt->copy()->addDays(5);

            $this->markMissing($referenceNumber, $processor, $missingAt, 'Could not be located during weekly shelf check before registry audit.');
            $this->markFound($referenceNumber, $processor, $foundAt, 'Recovered from the meeting room return tray and re-shelved.');
        }
    }

    private function recordLifecycleEvent(
        LegalFile $file,
        string $type,
        ?string $previousStatus,
        string $newStatus,
        User $processor,
        Carbon $occurredAt,
        ?string $notes = null,
        ?int $shelfId = null,
        ?string $batchId = null,
        ?int $employeeId = null,
        ?int $receiverId = null,
    ): void {
        $movement = FileMovement::query()->create([
            'batch_id' => $batchId,
            'legal_file_id' => $file->id,
            'type' => $type,
            'employee_id' => $employeeId,
            'receiver_id' => $receiverId,
            'previous_holder_id' => null,
            'processed_by' => $processor->id,
            'shelf_id' => $shelfId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);

        DB::table('file_movements')->where('id', $movement->id)->update([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function updateFileState(
        LegalFile $file,
        string $status,
        ?int $shelfId,
        User $processor,
        Carbon $occurredAt,
    ): void {
        DB::table('legal_files')->where('id', $file->id)->update([
            'status' => $status,
            'shelf_id' => $shelfId,
            'current_holder_id' => null,
            'updated_by' => $processor->id,
            'updated_at' => $occurredAt,
        ]);

        $file->status = $status;
        $file->shelf_id = $shelfId;
        $file->current_holder_id = null;
    }

    private function storeFile(
        LegalFile $file,
        Shelf $shelf,
        Staff $operator,
        User $processor,
        Carbon $occurredAt,
    ): void {
        $batch = new MovementBatch;
        $batch->id = (string) Str::uuid();
        $batch->type = 'pic_intake';
        $batch->employee_id = $operator->id;
        $batch->processed_by = $processor->id;
        $batch->processed_at = $occurredAt;
        $batch->save();

        DB::table('movement_batches')->where('id', $batch->id)->update([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
            'processed_at' => $occurredAt,
        ]);

        $this->recordLifecycleEvent(
            $file,
            'stored',
            LegalFile::STATUS_COMPLETED,
            LegalFile::STATUS_STORED,
            $processor,
            $occurredAt,
            'Stored to assigned shelf after completion approval.',
            $shelf->id,
            $batch->id,
        );

        $this->updateFileState($file, LegalFile::STATUS_STORED, $shelf->id, $processor, $occurredAt);
    }

    private function borrowFiles(array $referenceNumbers, Staff $employee, User $processor, Carbon $occurredAt, ?string $notes = null): void
    {
        $batch = new MovementBatch;
        $batch->id = (string) Str::uuid();
        $batch->type = 'borrow';
        $batch->employee_id = $employee->id;
        $batch->processed_by = $processor->id;
        $batch->processed_at = $occurredAt;
        $batch->save();

        DB::table('movement_batches')->where('id', $batch->id)->update([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
            'processed_at' => $occurredAt,
        ]);

        foreach ($referenceNumbers as $referenceNumber) {
            $file = $this->files[$referenceNumber];

            $movement = FileMovement::query()->create([
                'batch_id' => $batch->id,
                'legal_file_id' => $file->id,
                'type' => 'borrow',
                'employee_id' => $employee->id,
                'previous_holder_id' => $file->current_holder_id,
                'processed_by' => $processor->id,
                'shelf_id' => $file->shelf_id,
                'previous_status' => $file->status,
                'new_status' => LegalFile::STATUS_BORROWED,
                'notes' => $notes,
                'occurred_at' => $occurredAt,
            ]);

            DB::table('file_movements')->where('id', $movement->id)->update([
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            DB::table('legal_files')->where('id', $file->id)->update([
                'status' => LegalFile::STATUS_BORROWED,
                'current_holder_id' => $employee->id,
                'updated_by' => $processor->id,
                'updated_at' => $occurredAt,
            ]);

            $file->status = LegalFile::STATUS_BORROWED;
            $file->current_holder_id = $employee->id;
        }
    }

    private function returnFiles(
        array $referenceNumbers,
        Staff $returner,
        Staff $pic,
        User $processor,
        Carbon $occurredAt,
        ?string $notes = null,
    ): void {
        $batch = new MovementBatch;
        $batch->id = (string) Str::uuid();
        $batch->type = 'return';
        $batch->employee_id = $pic->id;
        $batch->processed_by = $processor->id;
        $batch->processed_at = $occurredAt;
        $batch->save();

        DB::table('movement_batches')->where('id', $batch->id)->update([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
            'processed_at' => $occurredAt,
        ]);

        foreach ($referenceNumbers as $referenceNumber) {
            $file = $this->files[$referenceNumber];

            $movement = FileMovement::query()->create([
                'batch_id' => $batch->id,
                'legal_file_id' => $file->id,
                'type' => 'return',
                'employee_id' => $returner->id,
                'receiver_id' => $pic->id,
                'previous_holder_id' => $file->current_holder_id,
                'processed_by' => $processor->id,
                'shelf_id' => $file->shelf_id,
                'previous_status' => $file->status,
                'new_status' => LegalFile::STATUS_STORED,
                'notes' => $notes,
                'occurred_at' => $occurredAt,
            ]);

            DB::table('file_movements')->where('id', $movement->id)->update([
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            DB::table('legal_files')->where('id', $file->id)->update([
                'status' => LegalFile::STATUS_STORED,
                'current_holder_id' => null,
                'updated_by' => $processor->id,
                'updated_at' => $occurredAt,
            ]);

            $file->status = LegalFile::STATUS_STORED;
            $file->current_holder_id = null;
        }
    }

    private function markMissing(string $referenceNumber, User $processor, Carbon $occurredAt, ?string $notes = null): void
    {
        $file = $this->files[$referenceNumber];

        $movement = FileMovement::query()->create([
            'legal_file_id' => $file->id,
            'type' => 'missing',
            'employee_id' => $file->current_holder_id,
            'previous_holder_id' => $file->current_holder_id,
            'processed_by' => $processor->id,
            'shelf_id' => $file->shelf_id,
            'previous_status' => $file->status,
            'new_status' => LegalFile::STATUS_MISSING,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);

        DB::table('file_movements')->where('id', $movement->id)->update([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        DB::table('legal_files')->where('id', $file->id)->update([
            'status' => LegalFile::STATUS_MISSING,
            'current_holder_id' => null,
            'updated_by' => $processor->id,
            'updated_at' => $occurredAt,
        ]);

        $file->status = LegalFile::STATUS_MISSING;
        $file->current_holder_id = null;
    }

    private function markFound(string $referenceNumber, User $processor, Carbon $occurredAt, ?string $notes = null): void
    {
        $file = $this->files[$referenceNumber];

        $movement = FileMovement::query()->create([
            'legal_file_id' => $file->id,
            'type' => 'found',
            'employee_id' => null,
            'previous_holder_id' => null,
            'processed_by' => $processor->id,
            'shelf_id' => $file->shelf_id,
            'previous_status' => $file->status,
            'new_status' => LegalFile::STATUS_STORED,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);

        DB::table('file_movements')->where('id', $movement->id)->update([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        DB::table('legal_files')->where('id', $file->id)->update([
            'status' => LegalFile::STATUS_STORED,
            'current_holder_id' => null,
            'updated_by' => $processor->id,
            'updated_at' => $occurredAt,
        ]);

        $file->status = LegalFile::STATUS_STORED;
        $file->current_holder_id = null;
    }

    private function seedImportRuns(): void
    {
        $runs = [
            ['legacy-registry-q1-2026.xlsx', 'completed', 86, 86, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 3, 8, 11, 15, 0, config('app.timezone'))],
            ['title-deed-vault-balance-20260321.xlsx', 'completed', 42, 42, 0, null, 'faridah.othman@tsangco.com.my', Carbon::create(2026, 3, 21, 16, 20, 0, config('app.timezone'))],
            ['old-conveyancing-index-april.csv', 'failed', 27, 0, 4, [
                'Row 7: reference number already exists.',
                'Duplicate reference number in workbook: CON/2026/0048.',
                'Row 19: purchaser and property are required.',
            ], 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 4, 5, 10, 10, 0, config('app.timezone'))],
            ['southern-branch-file-reconciliation.xlsx', 'completed', 58, 58, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 4, 19, 15, 5, 0, config('app.timezone'))],
            ['loan-redemption-backlog-may.xlsx', 'completed', 33, 33, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 5, 11, 9, 45, 0, config('app.timezone'))],
            ['probate-transfer-checklist.csv', 'failed', 19, 0, 3, [
                'Row 9: reference number already exists.',
                'Missing required column: Case Category.',
            ], 'faridah.othman@tsangco.com.my', Carbon::create(2026, 5, 27, 14, 25, 0, config('app.timezone'))],
            ['commercial-lease-docket-june.xlsx', 'completed', 24, 24, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 6, 4, 10, 30, 0, config('app.timezone'))],
            ['jb-vault-catchup-20260618.xlsx', 'completed', 31, 31, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 6, 18, 17, 10, 0, config('app.timezone'))],
            ['registry-cleanup-round-two.xlsx', 'completed', 15, 15, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 7, 2, 11, 40, 0, config('app.timezone'))],
            ['developer-panel-missing-columns.csv', 'failed', 11, 0, 2, [
                'Missing required column: Rack Code.',
                'The workbook contains no data rows.',
            ], 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 7, 14, 8, 55, 0, config('app.timezone'))],
            ['commercial-tenant-index-20260329.xlsx', 'completed', 18, 18, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 3, 29, 13, 15, 0, config('app.timezone'))],
            ['residential-vault-audit-20260430.xlsx', 'completed', 21, 21, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 4, 30, 10, 5, 0, config('app.timezone'))],
            ['strong-room-gap-analysis-may.csv', 'failed', 14, 0, 3, [
                'Row 6: storage location was not found.',
                'Row 8: reference number already exists.',
                'Duplicate reference number in workbook: TEN/2026/0014.',
            ], 'faridah.othman@tsangco.com.my', Carbon::create(2026, 5, 19, 16, 35, 0, config('app.timezone'))],
            ['johor-vault-rent-roll-20260630.xlsx', 'completed', 17, 17, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 6, 30, 15, 20, 0, config('app.timezone'))],
            ['registry-priority-batch-20260710.xlsx', 'completed', 29, 29, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 7, 10, 9, 25, 0, config('app.timezone'))],
            ['active-file-register-july-review.xlsx', 'completed', 22, 22, 0, null, 'faridah.othman@tsangco.com.my', Carbon::create(2026, 7, 23, 16, 45, 0, config('app.timezone'))],
            ['september-completion-queue.xlsx', 'completed', 12, 12, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 9, 8, 11, 20, 0, config('app.timezone'))],
        ];

        foreach ($runs as [$filename, $status, $total, $imported, $failed, $errors, $creatorEmail, $createdAt]) {
            $run = ImportRun::query()->create([
                'identifier' => (string) Str::uuid(),
                'original_filename' => $filename,
                'status' => $status,
                'total_rows' => $total,
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'errors' => $errors,
                'created_by' => $this->users[$creatorEmail]->id,
            ]);
            DB::table('import_runs')->where('id', $run->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function propertyDescription(int $index, string $development, string $address, string $matterType): string
    {
        $unitPrefix = match ($matterType) {
            'Commercial Lease' => 'Suite',
            'Tenancy Agreement' => 'Unit',
            'Probate & Transmission' => 'Lot',
            default => 'Unit',
        };

        if ($matterType === 'Probate & Transmission') {
            return sprintf(
                '%s %s, %s',
                $unitPrefix,
                1100 + $index,
                $address,
            );
        }

        $floor = (($index - 1) % 35) + 1;
        $stack = (($index * 3) % 18) + 1;

        return sprintf(
            '%s %d-%02d, %s, %s',
            $unitPrefix,
            $floor,
            $stack,
            $development,
            $address,
        );
    }
}
