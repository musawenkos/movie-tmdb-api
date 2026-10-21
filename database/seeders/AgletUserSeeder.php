<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgletUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jointheteam@aglet.co.za'],
            [
                'username' => 'jointheteam',
                'password' => '@TeamAglet',
            ]
        );
    }
}
