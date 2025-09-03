<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Generate paginated response
     */
    protected function paginatedResponse($paginatedData, $resourceClass, $message = 'Data retrieved successfully')
    {
        if ($paginatedData->total() === 0) {
            return response()->json([
                'message' => 'No data found',
                'data' => [],
                'pagination' => [
                    'current_page' => $paginatedData->currentPage(),
                    'per_page' => $paginatedData->perPage(),
                    'total' => 0,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'from' => null,
                    'to' => null
                ]
            ], 200);
        }

        return response()->json([
            'message' => $message,
            'data' => $resourceClass::collection($paginatedData->items()),
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage(),
                'has_more_pages' => $paginatedData->hasMorePages(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
                'next_page_url' => $paginatedData->nextPageUrl(),
                'prev_page_url' => $paginatedData->previousPageUrl()
            ]
        ], 200);
    }

    /**
     * Validate and sanitize pagination parameters
     */
    protected function getPaginationParams($request, $defaultPerPage = 15)
    {
        $perPage = $request->get('per_page', $defaultPerPage);
        $page = $request->get('page', 1);
        
        // Validate pagination parameters
        $perPage = min(max((int)$perPage, 1), 100); // Between 1 and 100
        $page = max((int)$page, 1); // At least 1
        
        return [$perPage, $page];
    }
}
