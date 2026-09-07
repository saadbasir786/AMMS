<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Paginate embedded history collections without renaming existing data fields. */
class MeetingWorkspacePagination
{
    public static function read($query, Request $request): array
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer']]);
        $paginator = $query->paginate(min(max($request->integer('per_page', 15), 1), 50));

        return JsonResource::collection($paginator)->response()->getData(true);
    }
}
