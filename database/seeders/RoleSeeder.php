<?php

namespace Database\Seeders;

use App\Services\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach (Access::ROLES as $code => $name) {
            $roles[] = ['kode' => $code, 'nama' => $name];
        }

        DB::table('roletbls')->insertOrIgnore($roles);
    }
}
