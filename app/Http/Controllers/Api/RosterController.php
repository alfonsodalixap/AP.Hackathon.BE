<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeRosterRequest;
use App\Http\Resources\RosterAnalysisResource;
use App\Services\RosterAnalysisService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RosterController extends Controller
{
    public function __construct(private readonly RosterAnalysisService $rosterService) {}

    public function analyze(AnalyzeRosterRequest $request): JsonResponse
    {
        try {
            $result = $this->rosterService->analyze($request->file('file'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new RosterAnalysisResource($result))->response();
    }
}
