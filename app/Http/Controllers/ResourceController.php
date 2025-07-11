<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Requests\UpdateResourceRequest;
use App\Services\ResourceService; // Import the new service
use App\Exceptions\ResourceException; // Import the custom exception
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResourceController extends Controller
{
    protected $resourceService;

    public function __construct(ResourceService $resourceService)
    {
        $this->resourceService = $resourceService;
        // Apply middleware here if you want to protect all resource routes.
        // For example, only admins can manage resources.
        // $this->middleware('auth:sanctum')->except(['index', 'show']); // Allow guests to view, but require auth for others
        // $this->middleware('admin')->only(['store', 'update', 'destroy']); // Custom admin middleware
    }

    /**
     * Display a listing of the resource, with optional filtering by feature name or IDs.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated."
            ], 401);
        }

        $category = $request->query('category');
        $featureName = $request->query('feature');
        $featureIds = $request->query('features');
        $minCapacity = $request->query('minCapacity');
        $maxCapacity = $request->query('maxCapacity');

        try {
            $query = Resource::with('features');
            if ($category) {
                $query->where('category', $category);
            }
            if ($featureName) {
                $query->whereHas('features', function ($q) use ($featureName) {
                    $q->where('name', $featureName);
                });
            }
            if ($featureIds) {
                foreach ((array)$featureIds as $fid) {
                    $query->whereHas('features', function ($q) use ($fid) {
                        $q->where('features.id', $fid);
                    });
                }
            }
            // Filter by minimum capacity
            if ($minCapacity !== null) {
                $query->where('capacity', '>=', $minCapacity);
            }
            // Filter by maximum capacity
            if ($maxCapacity !== null) {
                $query->where('capacity', '<=', $maxCapacity);
            }
            $resources = $query->get();
            // Ensure uuid is included in each resource
            $resourcesArray = $resources->map(function($resource) {
                return array_merge($resource->toArray(), ['uuid' => $resource->uuid]);
            });
            return response()->json([
                "success" => true,
                "resources" => $resourcesArray
            ]);
        } catch (\Exception $e) {
            Log::error('ResourceController@index failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while fetching resources."
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage, with features.
     */
    public function store(StoreResourceRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || ($user->user_type !== 'admin' && $user->role?->name !== 'admin')) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to create resources."
            ], 403);
        }
        try {
            $validatedData = $request->validated();
            $features = $request->input('features', []);
            
            // Use ResourceService to handle image upload properly
            $resource = $this->resourceService->createResource($validatedData);
            
            if (!empty($features)) {
                $resource->features()->sync($features);
            }
            $resourceArray = array_merge($resource->toArray(), ['uuid' => $resource->uuid]);
            return response()->json([
                "success" => true,
                "message" => "Resource created successfully.",
                "resource" => $resourceArray
            ], 201);
        } catch (\Exception $e) {
            Log::error('ResourceController@store failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while creating the resource."
            ], 500);
        }
    }

    /**
     * Display the specified resource with features.
     */
    public function show(Resource $resource): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated."
            ], 401);
        }
        try {
            $resource->load('features');
            $resourceArray = array_merge($resource->toArray(), ['uuid' => $resource->uuid]);
            return response()->json([
                "success" => true,
                "resource" => $resourceArray
            ]);
        } catch (\Exception $e) {
            Log::error('ResourceController@show failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while fetching the resource."
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage, including features.
     */
    public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $user = Auth::user();
        if (!$user || ($user->user_type !== 'admin' && $user->role?->name !== 'admin')) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to update resources."
            ], 403);
        }
        try {
            $validatedData = $request->validated();
            $features = $request->input('features', []);
            
            // Use ResourceService to handle image upload properly
            $resource = $this->resourceService->updateResource($resource, $validatedData);
            
            if (!empty($features)) {
                $resource->features()->sync($features);
            } else {
                $resource->features()->detach();
            }
            $resourceArray = array_merge($resource->toArray(), ['uuid' => $resource->uuid]);
            return response()->json([
                "success" => true,
                "message" => "Resource updated successfully.",
                "resource" => $resourceArray
            ]);
        } catch (\Exception $e) {
            Log::error('ResourceController@update failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while updating the resource."
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage, detaching features.
     */
    public function destroy(Resource $resource): JsonResponse
    {
        $user = Auth::user();
        if (!$user || ($user->user_type !== 'admin' && $user->role?->name !== 'admin')) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to delete resources."
            ], 403);
        }
        try {
            $resource->features()->detach();
            // Use ResourceService to handle image deletion properly
            $this->resourceService->deleteResource($resource);
            return response()->json([
                "success" => true,
                "message" => "Resource deleted successfully."
            ], 200);
        } catch (\Exception $e) {
            Log::error('ResourceController@destroy failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while deleting the resource."
            ], 500);
        }
    }

    /**
     * Get all resources for a given feature (by feature ID).
     */
    public function resourcesForFeature($featureId)
    {
        $feature = \App\Models\Feature::with('resources.features')->findOrFail($featureId);
        return response()->json($feature->resources);
    }

    public function getResourceBookings(Request $request, $id)
    {
        try {
            // Find the resource by its ID.            
            $resource = Resource::find($id);

            if (!$resource) {
                return response()->json([
                    'message' => 'Resource not found.'
                ], 404);
            }

           
            // bookings for this specific resource.
            // For example, if only resource owners or admins can see all bookings:
             if (!Auth::user()) {
                 return response()->json([
                    'message' => 'Unauthorized to view bookings for this resource.'
               ], 403);
            }

            // Load the bookings associated with the resource.
           
            $bookings = $resource->bookings()->get();

            // Return the bookings as a JSON response.
            
            return response()->json([
                'bookings' => $bookings
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error("Error fetching resource bookings for ID {$id}: " . $e->getMessage());

            // Return a generic error response
            return response()->json([
                'message' => 'An error occurred while fetching bookings.',
                'error' => $e->getMessage() 
            ], 500);
        }
    }

    public function trending(Request $request)
    {
        $limit = $request->query('limit', 6);

        $trending = \App\Models\Booking::select('resource_id', \DB::raw('COUNT(*) as bookings_count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('resource_id')
            ->orderByDesc('bookings_count')
            ->limit($limit)
            ->pluck('resource_id');

        $resources = \App\Models\Resource::whereIn('id', $trending)->get();

        Log::info('Trending resources: ' . json_encode($resources));

        return response()->json([
            'success' => true,
            'resources' => $resources
        ]);
    }
    
    public function getRecentlyBookedResources(Request $request)
    {
        $limit = $request->query('limit', 5);
        try {
            $resources = $this->resourceService->getRecentlyBookedResources(5);
            Log::info("recent booked resource". $resources);
            return response()->json([
                'success' => true,
                'resources' => $resources
            ]);
        } catch (\Exception $e) {
            Log::error('ResourceController@getRecentlyBookedResources failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching recently booked resources.'
            ], 500);
        }
    }

    /**
     * List all features (for frontend filter dropdowns).
     */
    public function allFeatures()
    {
        $features = \App\Models\Feature::all();
        Log::info('All features: ' . json_encode($features));
        return response()->json([
            'success' => true,
            'features' => $features
        ]);
    }

    /**
     * Lookup resource by UUID and return numeric ID for frontend compatibility.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function lookupByUuid(string $uuid): JsonResponse
    {
        $resource = Resource::where('uuid', $uuid)->first();
        
        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'resource' => [
                'id' => $resource->id,
                'uuid' => $resource->uuid,
                'name' => $resource->name,
                'description' => $resource->description,
                'location' => $resource->location,
                'capacity' => $resource->capacity,
                'type' => $resource->type,
                'category' => $resource->category,
                'is_active' => $resource->is_active,
                'created_at' => $resource->created_at ? $resource->created_at->toISOString() : null,
                'updated_at' => $resource->updated_at ? $resource->updated_at->toISOString() : null,
                'features' => $resource->features ? $resource->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'description' => $feature->description,
                    ];
                }) : []
            ]
        ]);
    }
}
