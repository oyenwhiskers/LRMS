<?php

namespace Database\Seeders;

use App\Models\FileMovement;
use App\Models\ImportRun;
use App\Models\LegalFile;
use App\Models\MovementBatch;
use App\Models\Position;
use App\Models\Room;
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

    /** @var array<string, Shelf> */
    private array $shelves = [];

    /** @var array<string, LegalFile> */
    private array $files = [];

    /** @var array<int, string> */
    private array $activeReferences = [];

    public function run(): void
    {
        $this->today = Carbon::create(2026, 7, 24, 10, 0, 0, config('app.timezone'));

        $this->resetDemoData();
        $this->seedPositions();
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
        DB::table('legal_files')->truncate();
        DB::table('system_sequences')->truncate();
        DB::table('shelves')->truncate();
        DB::table('cabinets')->truncate();
        DB::table('rooms')->truncate();
        DB::table('users')->where('role', User::ROLE_USER)->delete();
        DB::table('staff')->truncate();
        DB::table('permission_position')->truncate();
        DB::table('positions')->truncate();

        Schema::enableForeignKeyConstraints();
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
                    'storage.view', 'storage.manage', 'files.view', 'files.create', 'files.update', 'files.archive',
                    'labels.print', 'movements.borrow', 'movements.return', 'movements.history', 'missing.manage',
                    'imports.view', 'imports.create', 'exports.files', 'exports.movements', 'audit.view',
                ],
            ],
            [
                'name' => 'Senior Conveyancing Executive',
                'slug' => 'senior-conveyancing-executive',
                'description' => 'Handles subsale, loan and perfection matters for residential and commercial property.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'files.update', 'labels.print',
                    'movements.borrow', 'movements.return', 'movements.history',
                ],
            ],
            [
                'name' => 'Conveyancing Clerk',
                'slug' => 'conveyancing-clerk',
                'description' => 'Maintains file movement readiness, completions and banking documents.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'labels.print',
                    'movements.borrow', 'movements.return', 'movements.history',
                ],
            ],
            [
                'name' => 'Corporate Legal Executive',
                'slug' => 'corporate-legal-executive',
                'description' => 'Supports corporate transactions, shareholder documentation and board approvals.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'files.create', 'files.update', 'labels.print', 'movements.history',
                ],
            ],
            [
                'name' => 'Litigation Secretary',
                'slug' => 'litigation-secretary',
                'description' => 'Coordinates litigation bundles, court papers and service copies.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'labels.print', 'movements.borrow', 'movements.return', 'movements.history',
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
                    'dashboard.view', 'files.view', 'labels.print', 'movements.borrow', 'movements.return',
                ],
            ],
            [
                'name' => 'Trainee Solicitor',
                'slug' => 'trainee-solicitor',
                'description' => 'Reviews transactional documents and attends to supervised file work.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'movements.borrow', 'movements.return', 'movements.history',
                ],
            ],
            [
                'name' => 'Records Officer',
                'slug' => 'records-officer',
                'description' => 'Performs daily filing, shelving, retrieval and discrepancy checks.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'staff.view', 'staff.qr_print', 'storage.view', 'files.view',
                    'labels.print', 'movements.borrow', 'movements.return', 'movements.history', 'missing.manage',
                ],
            ],
            [
                'name' => 'Office Services Assistant',
                'slug' => 'office-services-assistant',
                'description' => 'Supports registry runs, courier dispatches and off-site storage requests.',
                'is_active' => true,
                'permissions' => [
                    'dashboard.view', 'files.view', 'movements.borrow', 'movements.return',
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
            'RG-A' => [
                'name' => 'Main Registry',
                'cabinets' => [
                    'A1' => ['name' => 'Residential Conveyancing Bay', 'shelves' => ['S1' => 'Shelf Alpha', 'S2' => 'Shelf Bravo', 'S3' => 'Shelf Charlie']],
                    'A2' => ['name' => 'Loan Documentation Bay', 'shelves' => ['S1' => 'Shelf Delta', 'S2' => 'Shelf Echo', 'S3' => 'Shelf Foxtrot']],
                    'A3' => ['name' => 'Perfection Matters Bay', 'shelves' => ['S1' => 'Shelf Golf', 'S2' => 'Shelf Hotel', 'S3' => 'Shelf India']],
                ],
            ],
            'RG-B' => [
                'name' => 'Strong Room',
                'cabinets' => [
                    'B1' => ['name' => 'Title Deed Vault', 'shelves' => ['S1' => 'Shelf Jade', 'S2' => 'Shelf Kappa', 'S3' => 'Shelf Lima']],
                    'B2' => ['name' => 'Commercial Matters Vault', 'shelves' => ['S1' => 'Shelf Meranti', 'S2' => 'Shelf Nusa', 'S3' => 'Shelf Omega']],
                    'B3' => ['name' => 'Probate & Estate Vault', 'shelves' => ['S1' => 'Shelf Pertiwi', 'S2' => 'Shelf Quartz', 'S3' => 'Shelf Rimba']],
                ],
            ],
            'RG-C' => [
                'name' => 'Closed Files Annex',
                'cabinets' => [
                    'C1' => ['name' => 'Archived Residential Files', 'shelves' => ['S1' => 'Shelf Selatan', 'S2' => 'Shelf Timur', 'S3' => 'Shelf Utara']],
                    'C2' => ['name' => 'Archived Corporate Files', 'shelves' => ['S1' => 'Shelf Vanda', 'S2' => 'Shelf Wira', 'S3' => 'Shelf Xenia']],
                    'C3' => ['name' => 'Off-Site Transfer Staging', 'shelves' => ['S1' => 'Shelf Yakin', 'S2' => 'Shelf Zamrud', 'S3' => 'Shelf Angsana']],
                ],
            ],
            'JB-V' => [
                'name' => 'Johor Bahru Deeds Vault',
                'cabinets' => [
                    'J1' => ['name' => 'Johor Residential Holdings', 'shelves' => ['S1' => 'Shelf Bakawali', 'S2' => 'Shelf Cempaka', 'S3' => 'Shelf Dahlia']],
                    'J2' => ['name' => 'Southern Commercial Holdings', 'shelves' => ['S1' => 'Shelf Enggang', 'S2' => 'Shelf Fajar', 'S3' => 'Shelf Gemilang']],
                ],
            ],
        ];

        foreach ($definitions as $roomCode => $roomDefinition) {
            $room = Room::query()->create([
                'code' => $roomCode,
                'name' => $roomDefinition['name'],
                'is_active' => true,
            ]);

            foreach ($roomDefinition['cabinets'] as $cabinetCode => $cabinetDefinition) {
                $cabinetId = DB::table('cabinets')->insertGetId([
                    'room_id' => $room->id,
                    'code' => $cabinetCode,
                    'name' => $cabinetDefinition['name'],
                    'is_active' => true,
                    'created_at' => $this->today,
                    'updated_at' => $this->today,
                ]);

                foreach ($cabinetDefinition['shelves'] as $shelfCode => $shelfName) {
                    $shelf = Shelf::query()->create([
                        'cabinet_id' => $cabinetId,
                        'code' => $shelfCode,
                        'name' => $shelfName,
                        'is_active' => true,
                    ]);

                    $this->shelves["{$roomCode}|{$cabinetCode}|{$shelfCode}"] = $shelf;
                }
            }
        }
    }

    private function seedFiles(): void
    {
        $matterBlueprints = [
            ['prefix' => 'CON', 'matter' => 'Subsale SPA', 'with_loan' => true],
            ['prefix' => 'CON', 'matter' => 'Loan Documentation', 'with_loan' => true],
            ['prefix' => 'CON', 'matter' => 'Perfection of Transfer', 'with_loan' => false],
            ['prefix' => 'CON', 'matter' => 'Perfection of Charge', 'with_loan' => true],
            ['prefix' => 'CON', 'matter' => 'Discharge of Charge', 'with_loan' => true],
            ['prefix' => 'TEN', 'matter' => 'Tenancy Agreement', 'with_loan' => false],
            ['prefix' => 'COM', 'matter' => 'Commercial Lease', 'with_loan' => false],
            ['prefix' => 'EST', 'matter' => 'Probate & Transmission', 'with_loan' => false],
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

        for ($index = 1; $index <= 138; $index++) {
            $blueprint = $matterBlueprints[($index - 1) % count($matterBlueprints)];
            $isCorporate = in_array($blueprint['prefix'], ['COM'], true) || $index % 7 === 0;
            $purchaserPool = $isCorporate ? $corporatePurchasers : $individualPurchasers;
            $development = $developments[($index - 1) % count($developments)];
            $shelfKey = $activeShelfKeys[($index - 1) % count($activeShelfKeys)];
            $referenceNumber = sprintf('%s/2026/%04d', $blueprint['prefix'], $index);
            $createdAt = $index > 133
                ? $this->today->copy()->setTime(9 + ($index % 4), ($index * 7) % 60)
                : Carbon::create(2026, 2, 3, 9, 0, 0, config('app.timezone'))->addDays($index);

            $property = $this->propertyDescription($index, $development[0], $development[1], $blueprint['matter']);
            $file = new LegalFile;
            $file->file_identifier = LegalFile::nextIdentifier();
            $file->qr_identifier = (string) Str::uuid();
            $file->reference_number = $referenceNumber;
            $file->loan_reference = $blueprint['with_loan'] ? $this->loanReference($index) : null;
            $file->purchaser = $purchaserPool[($index - 1) % count($purchaserPool)];
            $file->vendor = $vendors[($index + 2) % count($vendors)];
            $file->property = $property;
            $file->matter_type = $blueprint['matter'];
            $file->important_date = $createdAt->copy()->addDays(30 + ($index % 45))->toDateString();
            $file->person_in_charge_id = $this->staff[$personInChargeNumbers[($index - 1) % count($personInChargeNumbers)]]->id;
            $file->shelf_id = $this->shelves[$shelfKey]->id;
            $file->status = LegalFile::STATUS_AVAILABLE;
            $file->created_by = $this->users['aisyah.karim@tsangco.com.my']->id;
            $file->updated_by = $this->users['aisyah.karim@tsangco.com.my']->id;
            $file->save();

            DB::table('legal_files')->where('id', $file->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $file->refresh();
            $this->files[$referenceNumber] = $file;
            $this->activeReferences[] = $referenceNumber;
        }

        for ($index = 139; $index <= 150; $index++) {
            $blueprint = $matterBlueprints[($index - 1) % count($matterBlueprints)];
            $development = $developments[($index - 1) % count($developments)];
            $shelfKey = $activeShelfKeys[($index - 1) % count($activeShelfKeys)];
            $createdAt = Carbon::create(2025, 9, 8, 10, 0, 0, config('app.timezone'))->addDays($index);
            $archivedAt = $createdAt->copy()->addMonths(7)->addDays(($index % 18) + 10);

            $file = new LegalFile;
            $file->file_identifier = LegalFile::nextIdentifier();
            $file->qr_identifier = (string) Str::uuid();
            $file->reference_number = sprintf('%s/2025/%04d', $blueprint['prefix'], $index - 120);
            $file->loan_reference = $blueprint['with_loan'] ? $this->loanReference($index) : null;
            $file->purchaser = $individualPurchasers[($index - 1) % count($individualPurchasers)];
            $file->vendor = $vendors[($index + 5) % count($vendors)];
            $file->property = $this->propertyDescription($index, $development[0], $development[1], $blueprint['matter']);
            $file->matter_type = $blueprint['matter'];
            $file->important_date = $createdAt->copy()->addDays(45)->toDateString();
            $file->person_in_charge_id = $this->staff['TC17008']->id;
            $file->shelf_id = $this->shelves[$shelfKey]->id;
            $file->status = LegalFile::STATUS_AVAILABLE;
            $file->archived_at = $archivedAt;
            $file->created_by = $this->users['aisyah.karim@tsangco.com.my']->id;
            $file->updated_by = $this->users['aisyah.karim@tsangco.com.my']->id;
            $file->save();

            DB::table('legal_files')->where('id', $file->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $archivedAt,
            ]);
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

        $references = $this->activeReferences;

        foreach (array_chunk(array_slice($references, 0, 30), 3) as $chunkIndex => $chunk) {
            $borrowedAt = Carbon::create(2026, 3, 3, 10, 15, 0, config('app.timezone'))->addDays($chunkIndex * 4);
            $returnedAt = $borrowedAt->copy()->addDays(7 + ($chunkIndex % 3));
            $employee = $borrowers[$chunkIndex % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Borrowed for stamping bundle preparation and client signing appointment.");
            $this->returnFiles($chunk, $employee, $processor, $returnedAt, "Returned after execution set and duplicate copies were filed.");
        }

        foreach (array_chunk(array_slice($references, 30, 20), 4) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 2) % count($borrowers)];
            $processor = $processors[($chunkIndex + 1) % count($processors)];
            $firstBorrow = Carbon::create(2026, 4, 18, 9, 20, 0, config('app.timezone'))->addDays($chunkIndex * 5);
            $firstReturn = $firstBorrow->copy()->addDays(6);
            $secondBorrow = $firstReturn->copy()->addDays(10);
            $secondReturn = $secondBorrow->copy()->addDays(5 + ($chunkIndex % 2));

            $this->borrowFiles($chunk, $employee, $processor, $firstBorrow, "Retrieved for financier execution and solicitor certification.");
            $this->returnFiles($chunk, $employee, $processor, $firstReturn, "Refiled after first drawdown documents were completed.");
            $this->borrowFiles($chunk, $employee, $processor, $secondBorrow, "Borrowed again for perfection follow-up and undertaking review.");
            $this->returnFiles($chunk, $employee, $processor, $secondReturn, "Returned after status update letter and lodged forms were copied.");
        }

        foreach (array_chunk(array_slice($references, 50, 10), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 4) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $borrowedAt = Carbon::create(2026, 5, 9, 11, 10, 0, config('app.timezone'))->addDays($chunkIndex * 6);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Borrowed pending long-form completion, bank undertakings and redemption statements.");
        }

        foreach (array_chunk(array_slice($references, 60, 8), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 1) % count($borrowers)];
            $processor = $processors[($chunkIndex + 2) % count($processors)];
            $borrowedAt = Carbon::create(2026, 7, 5, 14, 5, 0, config('app.timezone'))->addDays($chunkIndex * 2);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Borrowed for imminent completion meeting and disbursement verification.");
        }

        foreach (array_chunk(array_slice($references, 68, 6), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 6) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $borrowedAt = Carbon::create(2026, 6, 20, 9, 40, 0, config('app.timezone'))->addDays($chunkIndex * 3);
            $returnedAt = $borrowedAt->copy()->addDays(4);
            $missingAt = $returnedAt->copy()->addDays(18);

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Borrowed for redemption and requisition reply review.");
            $this->returnFiles($chunk, $employee, $processor, $returnedAt, "Returned to shelf after follow-up with land office bundle.");

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
            $borrowedAt = $this->today->copy()->subDay()->setTime(15, 20 + ($chunkIndex * 7));

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Taken out for final client execution before same-day return.");
            $this->returnFiles($chunk, $employee, $processor, $this->today->copy()->setTime(9, 15 + ($chunkIndex * 12)), "Returned this morning after client acknowledgment and indexing.");
        }

        foreach (array_chunk(array_slice($references, 78, 6), 2) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 8) % count($borrowers)];
            $processor = $processors[$chunkIndex % count($processors)];
            $this->borrowFiles(
                $chunk,
                $employee,
                $processor,
                $this->today->copy()->setTime(10 + $chunkIndex, 10 + ($chunkIndex * 8)),
                "Borrowed today for urgent signing, completion payment advice and bank presentation.",
            );
        }

        foreach (array_chunk(array_slice($references, 84, 18), 3) as $chunkIndex => $chunk) {
            $employee = $borrowers[($chunkIndex + 5) % count($borrowers)];
            $processor = $processors[($chunkIndex + 2) % count($processors)];
            $borrowedAt = Carbon::create(2026, 5, 28, 9, 0, 0, config('app.timezone'))->addDays($chunkIndex * 3);
            $returnedAt = $borrowedAt->copy()->addDays(2 + ($chunkIndex % 4));

            $this->borrowFiles($chunk, $employee, $processor, $borrowedAt, "Borrowed for certified true copy extraction, execution and requisition handling.");
            $this->returnFiles($chunk, $employee, $processor, $returnedAt, "Returned after pending correspondences were uploaded and paper copies were stamped.");
        }

        foreach (array_slice($references, 102, 4) as $index => $referenceNumber) {
            $processor = $processors[$index % count($processors)];
            $missingAt = Carbon::create(2026, 6, 15, 16, 20, 0, config('app.timezone'))->addDays($index);
            $foundAt = $missingAt->copy()->addDays(5);

            $this->markMissing($referenceNumber, $processor, $missingAt, 'Could not be located during weekly shelf check before registry audit.');
            $this->markFound($referenceNumber, $processor, $foundAt, 'Recovered from the meeting room return tray and re-shelved.');
        }
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

    private function returnFiles(array $referenceNumbers, Staff $employee, User $processor, Carbon $occurredAt, ?string $notes = null): void
    {
        $batch = new MovementBatch;
        $batch->id = (string) Str::uuid();
        $batch->type = 'return';
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
                'type' => 'return',
                'employee_id' => $employee->id,
                'previous_holder_id' => $file->current_holder_id,
                'processed_by' => $processor->id,
                'shelf_id' => $file->shelf_id,
                'previous_status' => $file->status,
                'new_status' => LegalFile::STATUS_AVAILABLE,
                'notes' => $notes,
                'occurred_at' => $occurredAt,
            ]);

            DB::table('file_movements')->where('id', $movement->id)->update([
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            DB::table('legal_files')->where('id', $file->id)->update([
                'status' => LegalFile::STATUS_AVAILABLE,
                'current_holder_id' => null,
                'updated_by' => $processor->id,
                'updated_at' => $occurredAt,
            ]);

            $file->status = LegalFile::STATUS_AVAILABLE;
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
            'new_status' => LegalFile::STATUS_AVAILABLE,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);

        DB::table('file_movements')->where('id', $movement->id)->update([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        DB::table('legal_files')->where('id', $file->id)->update([
            'status' => LegalFile::STATUS_AVAILABLE,
            'current_holder_id' => null,
            'updated_by' => $processor->id,
            'updated_at' => $occurredAt,
        ]);

        $file->status = LegalFile::STATUS_AVAILABLE;
        $file->current_holder_id = null;
    }

    private function seedImportRuns(): void
    {
        $runs = [
            ['legacy-registry-q1-2026.xlsx', 'completed', 86, 86, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 3, 8, 11, 15, 0, config('app.timezone'))],
            ['title-deed-vault-balance-20260321.xlsx', 'completed', 42, 42, 0, null, 'faridah.othman@tsangco.com.my', Carbon::create(2026, 3, 21, 16, 20, 0, config('app.timezone'))],
            ['old-conveyancing-index-april.csv', 'failed', 27, 0, 4, [
                'Row 7: reference number already exists.',
                'Row 13: storage location was not found.',
                'Duplicate reference number in workbook: CON/2026/0048.',
                'Row 19: purchaser and property are required.',
            ], 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 4, 5, 10, 10, 0, config('app.timezone'))],
            ['southern-branch-file-reconciliation.xlsx', 'completed', 58, 58, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 4, 19, 15, 5, 0, config('app.timezone'))],
            ['loan-redemption-backlog-may.xlsx', 'completed', 33, 33, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 5, 11, 9, 45, 0, config('app.timezone'))],
            ['probate-transfer-checklist.csv', 'failed', 19, 0, 3, [
                'Row 4: storage location was not found.',
                'Row 9: reference number already exists.',
                'Missing required column: Room Code.',
            ], 'faridah.othman@tsangco.com.my', Carbon::create(2026, 5, 27, 14, 25, 0, config('app.timezone'))],
            ['commercial-lease-docket-june.xlsx', 'completed', 24, 24, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 6, 4, 10, 30, 0, config('app.timezone'))],
            ['jb-vault-catchup-20260618.xlsx', 'completed', 31, 31, 0, null, 'aisyah.karim@tsangco.com.my', Carbon::create(2026, 6, 18, 17, 10, 0, config('app.timezone'))],
            ['registry-cleanup-round-two.xlsx', 'completed', 15, 15, 0, null, 'kelvin.low@tsangco.com.my', Carbon::create(2026, 7, 2, 11, 40, 0, config('app.timezone'))],
            ['developer-panel-missing-columns.csv', 'failed', 11, 0, 2, [
                'Missing required column: Shelf Code.',
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

    private function loanReference(int $index): string
    {
        $banks = ['MBB', 'CIMB', 'PBB', 'RHB', 'HLB', 'UOB', 'AMB', 'OCBC'];

        return sprintf(
            '%s/HL/%s/%04d',
            $banks[($index - 1) % count($banks)],
            Carbon::create(2026, 1, 1)->addDays($index)->format('ym'),
            1100 + $index,
        );
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
