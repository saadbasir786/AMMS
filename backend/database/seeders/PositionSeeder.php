<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Support\PositionType;
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
            'position_type' => PositionType::HIGH_AUTHORITY,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'title' => 'رئیس دفتر مقام',
            'code' => 'AUTH-OFFICE-DIRECTOR',
            'position_type' => PositionType::AUTHORITY_OFFICE,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['AUTH-OFFICE'],
            'title' => 'سکرتر اجرائیوی مقام',
            'code' => 'EXEC-SECRETARY',
            'position_type' => PositionType::EXECUTIVE_SECRETARY,
            'status' => 'ACTIVE',
        ]);

        // 2. Deputy Presidency Position
        Position::create([
            'organizational_unit_id' => $unitByCode['DEP-PRES-FIN'],
            'title' => 'معاون مالی و اداری',
            'code' => 'DEP-PRES-FIN-POS',
            'position_type' => PositionType::DEPUTY_DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        // 3. Technology Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['TECH'],
            'title' => 'رئیس تکنالوژی معلوماتی',
            'code' => 'TECH-DIR-POS',
            'position_type' => PositionType::DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        // Deputy Director placed in DEPUTY_DIRECTORATE unit (TECH-DEP)
        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-DEP'],
            'title' => 'معاون ریاست تکنالوژی',
            'code' => 'TECH-DEP-POS',
            'position_type' => PositionType::DEPUTY_DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'title' => 'مدیر عمومی اجرائیه',
            'code' => 'TECH-EXEC-POS',
            'position_type' => PositionType::GENERAL_MANAGER,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-AMIR-NET'],
            'title' => 'آمر شبکه و سیستم‌ها',
            'code' => 'TECH-AMIR-NET-POS',
            'position_type' => PositionType::AMIR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-INFRA'],
            'title' => 'مدیر عمومی شبکه',
            'code' => 'TECH-GM-NET-POS',
            'position_type' => PositionType::GENERAL_MANAGER,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-MGT-NET'],
            'title' => 'مدیر شبکه',
            'code' => 'TECH-MGT-NET-POS',
            'position_type' => PositionType::MANAGER,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-AMIR-SW'],
            'title' => 'آمر نرم‌افزار',
            'code' => 'TECH-AMIR-SW-POS',
            'position_type' => PositionType::AMIR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-SW'],
            'title' => 'انجنیر نرم‌افزار',
            'code' => 'TECH-SW-ENG-POS',
            'position_type' => PositionType::EMPLOYEE,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['TECH-EXEC'],
            'title' => 'مسؤول اداری',
            'code' => 'TECH-ADMIN-POS',
            'position_type' => PositionType::EMPLOYEE,
            'status' => 'ACTIVE',
        ]);

        // 4. Finance Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['FIN'],
            'title' => 'رئیس مالی و حسابی',
            'code' => 'FIN-DIR-POS',
            'position_type' => PositionType::DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['FIN-DEP'],
            'title' => 'معاون ریاست مالی',
            'code' => 'FIN-DEP-POS',
            'position_type' => PositionType::DEPUTY_DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['FIN-EXEC'],
            'title' => 'مدیر عمومی اجرائیه',
            'code' => 'FIN-EXEC-POS',
            'position_type' => PositionType::GENERAL_MANAGER,
            'status' => 'ACTIVE',
        ]);

        // 5. Human Resources Directorate Positions
        Position::create([
            'organizational_unit_id' => $unitByCode['HR'],
            'title' => 'رئیس منابع بشری',
            'code' => 'HR-DIR-POS',
            'position_type' => PositionType::DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['HR-DEP'],
            'title' => 'معاون ریاست منابع بشری',
            'code' => 'HR-DEP-POS',
            'position_type' => PositionType::DEPUTY_DIRECTOR,
            'status' => 'ACTIVE',
        ]);

        Position::create([
            'organizational_unit_id' => $unitByCode['HR-EXEC'],
            'title' => 'مدیر عمومی اجرائیه',
            'code' => 'HR-EXEC-POS',
            'position_type' => PositionType::GENERAL_MANAGER,
            'status' => 'ACTIVE',
        ]);
    }
}
