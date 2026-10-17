<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        // 1. Admin
        User::create([
            'full_name' => 'Ahmed Admin',
            'email' => 'admin@collabspace.ai',
            'phone' => '+201000000001',
            'password' => $password,
            'job_title' => 'Chief Technology Officer (CTO)',
            'years_of_experience' => 12,
            'availability_status' => 'Available',
            'system_role' => 'Admin',
        ]);

        // 2. Project Managers
        User::create([
            'full_name' => 'Sarah Connor',
            'email' => 'pm@collabspace.ai',
            'phone' => '+201000000002',
            'password' => $password,
            'job_title' => 'Lead Technical Project Manager',
            'years_of_experience' => 8,
            'availability_status' => 'Available',
            'system_role' => 'ProjectManager',
        ]);

        User::create([
            'full_name' => 'Ahmed Hidar',
            'email' => 'ahmedheedar@gmail.com',
            'phone' => '+201000000003',
            'password' => $password,
            'job_title' => 'Agile Scrum Master',
            'years_of_experience' => 6,
            'availability_status' => 'Busy',
            'system_role' => 'ProjectManager',
        ]);

        // 3. Team Members
        $members = [
            [
                'full_name' => 'Kareem Tarek',
                'email' => 'backend@collabspace.ai',
                'phone' => '+201000000004',
                'job_title' => 'Senior Backend Engineer (Laravel / PHP)',
                'years_of_experience' => 7,
                'availability_status' => 'Available',
            ],
            [
                'full_name' => 'Nour El-Din',
                'email' => 'frontend@collabspace.ai',
                'phone' => '+201000000005',
                'job_title' => 'Senior Frontend Developer (Vue / React)',
                'years_of_experience' => 5,
                'availability_status' => 'Available',
            ],
            [
                'full_name' => 'Salma Mansour',
                'email' => 'uiux@collabspace.ai',
                'phone' => '+201000000006',
                'job_title' => 'Product & UI/UX Designer',
                'years_of_experience' => 4,
                'availability_status' => 'Away',
            ],
            [
                'full_name' => 'Mostafa Hassan',
                'email' => 'qa@collabspace.ai',
                'phone' => '+201000000007',
                'job_title' => 'Quality Assurance Lead',
                'years_of_experience' => 5,
                'availability_status' => 'Available',
            ],
            [
                'full_name' => 'Youssef Adel',
                'email' => 'devops@collabspace.ai',
                'phone' => '+201000000008',
                'job_title' => 'DevOps & Cloud Architect',
                'years_of_experience' => 6,
                'availability_status' => 'Busy',
            ],
            [
                'full_name' => 'Dina Fathy',
                'email' => 'marketing@collabspace.ai',
                'phone' => '+201000000009',
                'job_title' => 'Product Growth & Marketing',
                'years_of_experience' => 3,
                'availability_status' => 'Offline',
            ],
        ];

        foreach ($members as $member) {
            User::create([
                'full_name' => $member['full_name'],
                'email' => $member['email'],
                'phone' => $member['phone'],
                'password' => $password,
                'job_title' => $member['job_title'],
                'years_of_experience' => $member['years_of_experience'],
                'availability_status' => $member['availability_status'],
                'system_role' => 'TeamMember',
            ]);
        }
    }
}
