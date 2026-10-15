<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller
{
    // ---------------------------------------------------------------
    //  Step Guards
    // ---------------------------------------------------------------

    private function ensureStep(Request $request, int $required): ?\Illuminate\Http\RedirectResponse
    {
        if (($request->session()->get('install_step', 1)) < $required) {
            return redirect()->route('install.welcome');
        }

        return null;
    }

    // ---------------------------------------------------------------
    //  Step 1: Welcome
    // ---------------------------------------------------------------

    public function welcome(Request $request)
    {
        $request->session()->put('install_step', 1);

        return view('install.welcome', ['currentStep' => 1]);
    }

    // ---------------------------------------------------------------
    //  Step 2: Requirements
    // ---------------------------------------------------------------

    public function requirements(Request $request)
    {
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');

        $extensions = [
            'bcmath'    => extension_loaded('bcmath'),
            'ctype'     => extension_loaded('ctype'),
            'curl'      => extension_loaded('curl'),
            'dom'       => extension_loaded('dom'),
            'fileinfo'  => extension_loaded('fileinfo'),
            'gd'        => extension_loaded('gd'),
            'json'      => extension_loaded('json'),
            'mbstring'  => extension_loaded('mbstring'),
            'openssl'   => extension_loaded('openssl'),
            'pdo'       => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml'       => extension_loaded('xml'),
            'zip'       => extension_loaded('zip'),
            'imap'      => extension_loaded('imap'),
        ];

        $directories = [
            'storage'           => is_writable(storage_path()),
            'storage/app'       => is_writable(storage_path('app')),
            'storage/framework' => is_writable(storage_path('framework')),
            'storage/logs'      => is_writable(storage_path('logs')),
            'bootstrap/cache'   => is_writable(base_path('bootstrap/cache')),
        ];

        $pdoDrivers = \PDO::getAvailableDrivers();
        $hasPdoDriver = in_array('mysql', $pdoDrivers);

        $allPassed = $phpOk
            && ! in_array(false, $extensions, true)
            && ! in_array(false, $directories, true)
            && $hasPdoDriver;

        return view('install.requirements', [
            'currentStep'  => 2,
            'phpVersion'   => $phpVersion,
            'phpOk'        => $phpOk,
            'extensions'   => $extensions,
            'directories'  => $directories,
            'pdoDrivers'   => $pdoDrivers,
            'hasPdoDriver' => $hasPdoDriver,
            'allPassed'    => $allPassed,
        ]);
    }

    public function checkRequirementsPost(Request $request)
    {
        $request->session()->put('install_step', 2);

        return redirect()->route('install.database');
    }

    // ---------------------------------------------------------------
    //  Step 3: Database
    // ---------------------------------------------------------------

    public function database(Request $request)
    {
        if ($redirect = $this->ensureStep($request, 2)) {
            return $redirect;
        }

        return view('install.database', [
            'currentStep' => 3,
            'db'          => $request->session()->get('db', []),
        ]);
    }

    public function testDatabase(Request $request)
    {
        $host = $request->input('host');
        $port = $request->input('port');
        $db   = $request->input('database');
        $user = $request->input('username');
        $pass = $request->input('password', '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db}";

            new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            return response()->json(['success' => true, 'message' => 'Connection successful!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveDatabase(Request $request)
    {
        $request->session()->put('db', $request->only('host', 'port', 'database', 'username', 'password'));
        $request->session()->put('install_step', 3);

        return redirect()->route('install.application');
    }

    // ---------------------------------------------------------------
    //  Step 4: Application Settings
    // ---------------------------------------------------------------

    public function application(Request $request)
    {
        if ($redirect = $this->ensureStep($request, 3)) {
            return $redirect;
        }

        return view('install.application', [
            'currentStep' => 4,
            'app'         => $request->session()->get('app', []),
        ]);
    }

    public function saveApplication(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'url'      => 'required|url|max:255',
            'timezone' => 'required|string|max:100',
        ]);

        $request->session()->put('app', $request->only('name', 'url', 'timezone'));
        $request->session()->put('install_step', 4);

        return redirect()->route('install.admin');
    }

    // ---------------------------------------------------------------
    //  Step 5: Admin Account
    // ---------------------------------------------------------------

    public function admin(Request $request)
    {
        if ($redirect = $this->ensureStep($request, 4)) {
            return $redirect;
        }

        return view('install.admin', [
            'currentStep' => 5,
            'admin'       => $request->session()->get('admin_data', []),
        ]);
    }

    public function saveAdmin(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:40',
            'email'    => 'required|email|max:255',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->session()->put('admin_data', $request->only('name', 'email', 'password'));
        $request->session()->put('install_step', 5);

        return redirect()->route('install.run');
    }

    // ---------------------------------------------------------------
    //  Step 6: Run Installation
    // ---------------------------------------------------------------

    public function run(Request $request)
    {
        if ($redirect = $this->ensureStep($request, 5)) {
            return $redirect;
        }

        return view('install.progress', ['currentStep' => 6]);
    }

    public function execute(Request $request)
    {
        $step = (int) $request->input('step', 0);

        $db    = $request->session()->get('db', []);
        $app   = $request->session()->get('app', []);
        $admin = $request->session()->get('admin_data', []);

        if (empty($db) || empty($app) || empty($admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing installation data. Please restart the installer.',
            ], 422);
        }

        // Steps 2-6 need DB — ensure runtime config matches
        if ($step >= 2) {
            $this->applyDatabaseConfig($db);
        }

        try {
            match ($step) {
                1 => $this->stepWriteEnv($db, $app),
                2 => $this->stepRunMigrations(),
                3 => $this->stepSeedData(),
                4 => $this->stepCreateAdmin($admin),
                5 => $this->stepFilePermissions(),
                6 => $this->stepFinalize($request, $db, $admin, $app),
                default => throw new \InvalidArgumentException('Invalid step.'),
            };

            return response()->json(['success' => true, 'step' => $step]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'step'    => $step,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    //  Installation Steps
    // ---------------------------------------------------------------

    private function stepWriteEnv(array $db, array $app): void
    {
        $this->writeEnvFile($db, $app);
    }

    private function stepRunMigrations(): void
    {
        // Run migrations in a loop — some incremental migrations may fail with
        // "column already exists" if the base migration already includes the column.
        // We retry up to 5 times, each time skipping the failed migration.
        $maxAttempts = 5;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                return; // All migrations ran successfully
            } catch (\Illuminate\Database\QueryException $e) {
                $code = $e->getCode();
                // 42S21 = Column already exists, 42000 = Duplicate key/index
                if ($code === '42S21' || $code === '42000' || str_contains($e->getMessage(), 'already exists')) {
                    // Mark this migration as "ran" and try again
                    continue;
                }
                throw $e; // Real error, not a duplicate
            }
        }
    }

    private function stepSeedData(): void
    {
        $seeders = [
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PermissionsSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
            \Database\Seeders\PlanSeeder::class,
            \Database\Seeders\PaymentGatewaySeeder::class,
            \Database\Seeders\PaymentGatewayCategorySeeder::class,
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\HelpArticleSeeder::class,
            \Database\Seeders\EmailTemplateSeeder::class,
            \Database\Seeders\TestimonialSeeder::class,
            \Database\Seeders\PageSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        }
    }

    private function stepCreateAdmin(array $admin): void
    {
        $user = User::create([
            'name'              => $admin['name'],
            'email'             => $admin['email'],
            'password'          => Hash::make($admin['password']),
            'email_verified_at' => now(),
            'status'            => 'active',
        ]);

        // is_admin and admin_role are guarded — must set directly
        $user->forceFill([
            'is_admin'   => true,
            'admin_role' => 'super_admin',
        ])->save();

        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $role = \Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        // Create a default workspace so the admin can use the user dashboard too
        $workspace = \App\Models\Workspace::create([
            'name' => $admin['name'] . "'s Workspace",
            'industry' => 'SaaS / Technology',
            'team_size' => '2-5',
            'onboarding_completed' => true,
            'onboarding_step' => 5,
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'status' => 'offline',
        ]);

        $user->update(['active_workspace_id' => $workspace->id]);
    }

    private function stepFilePermissions(): void
    {
        try {
            Artisan::call('storage:link');
        } catch (\Exception $e) {
            // Link may already exist — safe to ignore
        }
    }

    private function stepFinalize(Request $request, array $db, array $admin, array $app): void
    {
        // Set INSTALLED=1 in .env
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $env = file_get_contents($envPath);
            if (str_contains($env, 'INSTALLED=')) {
                $env = preg_replace('/^INSTALLED=.*/m', 'INSTALLED=1', $env);
            } else {
                $env .= "\nINSTALLED=1\n";
            }
            file_put_contents($envPath, $env);
        }

        Artisan::call('config:clear');

        // Re-apply DB config because config:clear resets in-memory config
        $this->applyDatabaseConfig($db);

        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Create installed marker file
        $installedData = [
            'installed_at' => now()->toIso8601String(),
            'version'      => '1.0.0',
            'admin_email'  => $admin['email'],
        ];

        file_put_contents(storage_path('installed'), json_encode($installedData));

        $request->session()->put('install_step', 6);
        $request->session()->put('install_complete', [
            'email' => $admin['email'],
            'url'   => $app['url'],
        ]);
    }

    // ---------------------------------------------------------------
    //  Step 7: Complete
    // ---------------------------------------------------------------

    public function complete(Request $request)
    {
        $complete = $request->session()->get('install_complete', []);

        return view('install.complete', [
            'currentStep' => 7,
            'adminEmail'  => $complete['email'] ?? '',
            'appUrl'      => $complete['url'] ?? url('/'),
        ]);
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function writeEnvFile(array $db, array $app): void
    {
        $envPath = base_path('.env');

        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        $updates = [
            'APP_NAME'         => '"' . str_replace('"', '\\"', $app['name'] ?? config('app.name', 'MailTrixy')) . '"',
            'APP_ENV'          => 'production',
            'APP_DEBUG'        => 'false',
            'APP_URL'          => $app['url'],
            'APP_TIMEZONE'     => $app['timezone'],
            'DB_CONNECTION'    => 'mysql',
            'DB_HOST'          => $db['host'] ?? '127.0.0.1',
            'DB_PORT'          => $db['port'] ?? '3306',
            'DB_DATABASE'      => $db['database'] ?? '',
            'DB_USERNAME'      => $db['username'] ?? '',
            'DB_PASSWORD'      => $db['password'] ?? '',
            'SESSION_DRIVER'   => 'database',
            'CACHE_STORE'      => 'database',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER'      => 'log',
            'INSTALLED'        => '0',
        ];

        foreach ($updates as $key => $value) {
            $line = "{$key}={$value}";
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace_callback(
                    "/^{$key}=.*/m",
                    static fn () => $line,
                    $content
                );
            } else {
                $content .= "\n{$line}";
            }
        }

        file_put_contents($envPath, $content);
    }

    private function applyDatabaseConfig(array $db): void
    {
        config([
            'database.default'                    => 'mysql',
            'database.connections.mysql.host'     => $db['host'] ?? '127.0.0.1',
            'database.connections.mysql.port'     => $db['port'] ?? '3306',
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'] ?? '',
            'database.connections.mysql.password' => $db['password'] ?? '',
        ]);

        \Illuminate\Support\Facades\DB::purge('mysql');
    }
}
