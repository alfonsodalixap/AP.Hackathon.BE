<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialsResource;
use App\Services\SecEdgarService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class FinancialsController extends Controller
{
    public function __construct(private readonly SecEdgarService $edgarService) {}

    public function show(string $ticker): JsonResponse
    {
        try {
            $result = $this->edgarService->getFinancials($ticker);
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return (new FinancialsResource($result))->response();
    }
}
