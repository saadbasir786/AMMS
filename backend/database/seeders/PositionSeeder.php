<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $unitByCode = OrganizationalUnit::query()->pluck('id', 'code')->all();

        // 1. High Authority & Office Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['AUTHORITY'],
            'title' => 'رئیس عمومی',
            'code' => 'HIGH-AUTHORITY',
            'position_type' => 'HIGH_AUTHORITY',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'title' => 'رئیس دفتر مقام',
            'code' => 'AUTH-OFFICE-DIRECTOR',
            'position_type' => 'AUTHORITY_OFFICE',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'title' => 'سکرتر اجرائیوی مقام',
            'code' => 'EXEC-SECRETARY',
            'position_type' => 'EXECUTIVE_SECRETARY',
            'status' => 'ACTIVE',
        ]);

        // 2. Deputy Presidency Position
        Position::create([
            'organizational_unit_id' => $unitByCode['DEP-PRES-FIN'],
            'title' => 'معاون مالی و اداری',
            'code' => 'DEP-PRES-FIN-POS',
            'position_type' => 'DEPUTY_DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        // 3. Technology Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['TECH'],
            'title' => 'رئیس تکنالوژی معلوماتی',
            'code' => 'TECH-DIR-POS',
            'position_type' => 'DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        // Deputy Director placed in DEPUTY_DIRECTORATE unit (TECH-DEP)
        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-DEP'],
            'title' => 'معاون ریاست تکنالوژی',
            'code' => 'TECH-DEP-POS',
            'position_type' => 'DEPUTY_DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'title' => 'مدیر عمومی اجرائیه تکنالوژی',
            'code' => 'TECH-EXEC-POS',
            'position_type' => 'EXECUTIVE_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-AMIR-NET'],
            'title' => 'آمر شبکه و سیستم‌ها',
            'code' => 'TECH-AMIR-NET-POS',
            'position_type' => 'AMIR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-INFRA'],
            'title' => 'مدیر عمومی شبکه و زیربنا',
            'code' => 'TECH-GM-NET-POS',
            'position_type' => 'GENERAL_MANAGER',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-MGT-NET'],
            'title' => 'مدیر عملیاتی شبکه',
            'code' => 'TECH-MGT-NET-POS',
            'position_type' => 'MANAGER',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-AMIR-SW'],
            'title' => 'آمر نرم‌افزار و سیستم‌ها',
            'code' => 'TECH-AMIR-SW-POS',
            'position_type' => 'AMIR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-SW'],
            'title' => 'انجنیر نرم‌افزار',
            'code' => 'TECH-SW-ENG-POS',
            'position_type' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'title' => 'مسؤول اداری',
            'code' => 'TECH-ADMIN-POS',
            'position_type' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        // 4. Finance Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['FIN'],
            'title' => 'رئیس مالی و حسابی',
            'code' => 'FIN-DIR-POS',
            'position_type' => 'DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['FIN-DEP'],
            'title' => 'معاون ریاست مالی',
            'code' => 'FIN-DEP-POS',
            'position_type' => 'DEPUTY_DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['FIN-EXEC'],
            'title' => 'مدیر عمومی اجرائیه مالی',
            'code' => 'FIN-EXEC-POS',
            'position_type' => 'EXECUTIVE_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);

        // 5. Human Resources Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['HR'],
            'title' => 'رئیس منابع بشری',
            'code' => 'HR-DIR-POS',
            'position_type' => 'DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['HR-DEP'],
            'title' => 'معاون ریاست منابع بشری',
            'code' => 'HR-DEP-POS',
            'position_type' => 'DEPUTY_DIRECTOR',
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['HR-EXEC'],
            'title' => 'مدیر عمومی اجرائیه منابع بشری',
            'code' => 'HR-EXEC-POS',
            'position_type' => 'EXECUTIVE_MANAGEMENT',
            'status' => 'ACTIVE',
        ]);
    }
}
