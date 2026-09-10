<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Support\AmmsRole;
use App\Support\OrganizationalUnitType;
use App\Support\PositionType;
use App\Support\SpecialCapability;
use Illuminate\Http\Request;

final class MetadataController extends Controller
{
    public function amms(Request $request)
    {
        abort_unless($request->user()?->isActive() === true, 401, 'UNAUTHORIZED');

        return response()->json([
            'success' => true,
            'data' => [
                'organizational_unit_types' => OrganizationalUnitType::all(),
                'formal_governance_unit_types' => OrganizationalUnitType::formalGovernance(),
                'position_types' => PositionType::all(),
                'roles' => AmmsRole::all(),
                'special_capabilities' => SpecialCapability::all(),
                'labels' => [
                    'organizational_unit_types' => OrganizationalUnitType::labels(),
                    'position_types' => PositionType::labels(),
                    'roles' => AmmsRole::labels(),
                ],
            ],
        ]);
    }
}
