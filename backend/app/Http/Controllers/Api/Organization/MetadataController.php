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
        $customRoles = \App\Models\AmmsRoleDefinition::query()->with('role')->where('role_kind', 'CUSTOM')->where('status', 'ACTIVE')->get();
        $roleLabels = array_merge(AmmsRole::labels(), $customRoles->mapWithKeys(fn ($definition) => [$definition->role->name => $definition->label])->all());

        return response()->json([
            'success' => true,
            'data' => [
                'organizational_unit_types' => OrganizationalUnitType::all(),
                'formal_governance_unit_types' => OrganizationalUnitType::formalGovernance(),
                'position_types' => PositionType::all(),
                'roles' => AmmsRole::all(),
                'system_roles' => AmmsRole::all(),
                'custom_roles' => $customRoles->map(fn ($definition) => ['name' => $definition->role->name, 'label' => $definition->label])->values()->all(),
                'special_capabilities' => SpecialCapability::all(),
                'labels' => [
                    'organizational_unit_types' => OrganizationalUnitType::labels(),
                    'position_types' => PositionType::labels(),
                    'roles' => $roleLabels,
                ],
            ],
        ]);
    }
}
