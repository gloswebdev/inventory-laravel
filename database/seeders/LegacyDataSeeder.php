<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserPermission;
use PDO;

class LegacyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Connect to old database directly using PDO
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;dbname=inventory_db', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            $this->command->error("Could not connect to legacy database: " . $e->getMessage());
            return;
        }

        $this->command->info('Migrating Users...');

        $stmt = $pdo->query("SELECT * FROM users");
        $legacyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($legacyUsers as $legacyUser) {
            $user = User::create([
                'username' => $legacyUser['username'],
                'name' => $legacyUser['username'], // Using username as name initially
                'password' => $legacyUser['password'], // Password hash should be compatible (bcrypt)
                'role' => $legacyUser['role'],
                'email' => null, // Legacy users didn't have email
            ]);

            // Migrate permissions for this user
            if ($user->role === 'user') {
                $permStmt = $pdo->prepare("SELECT page_key FROM user_permissions WHERE user_id = ?");
                $permStmt->execute([$legacyUser['id']]);
                $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($permissions as $pageKey) {
                    UserPermission::create([
                        'user_id' => $user->id,
                        'page_key' => $pageKey,
                    ]);
                }
            }
        }

        $this->command->info('Users migrated successfully!');
    }
}
