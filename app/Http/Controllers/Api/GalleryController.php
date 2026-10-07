<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    /**
     * Get active gallery categories.
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = GalleryCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'image',
                ])
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,

                        'image' => $category->image
                            ? Storage::disk('api_public')->url($category->image)
                            : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Gallery categories retrieved successfully.',
                'data' => $categories,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve gallery categories.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active gallery items.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = GalleryItem::query()
                ->where('is_active', true)
                ->with([
                    'category:id,name,image',
                ]);

            if ($request->filled('category_id')) {
                $query->where(
                    'category_id',
                    $request->integer('category_id')
                );
            }

            $items = $query
                ->latest('id')
                ->get([
                    'id',
                    'category_id',
                    'title',
                    'image',
                ])
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'category_id' => $item->category_id,
                        'title' => $item->title,

                        'image' => $item->image
                            ? Storage::disk('api_public')->url($item->image)
                            : null,

                        'category' => $item->category
                            ? [
                                'id' => $item->category->id,
                                'name' => $item->category->name,

                                'image' => $item->category->image
                                    ? Storage::disk('api_public')->url($item->category->image)
                                    : null,
                            ]
                            : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Gallery items retrieved successfully.',
                'data' => $items,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve gallery items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single active gallery item.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $item = GalleryItem::query()
                ->where('is_active', true)
                ->with([
                    'category:id,name,image',
                ])
                ->find($id);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Gallery item not found.',
                ], 404);
            }

            $data = [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'title' => $item->title,

                'image' => $item->image
                    ? Storage::disk('api_public')->url($item->image)
                    : null,

                'category' => $item->category
                    ? [
                        'id' => $item->category->id,
                        'name' => $item->category->name,

                        'image' => $item->category->image
                            ? Storage::disk('api_public')->url($item->category->image)
                            : null,
                    ]
                    : null,
            ];

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Gallery item retrieved successfully.',
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve gallery item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
