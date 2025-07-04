<?php

namespace App\Services;

use App\Exceptions\ResourceException;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResourceService
{
    /**
     * Get a listing of resources.
     * Can be extended to include filtering, sorting, pagination.
     *
     * @return Collection<Resource>
     */
    public function getAllResources(?string $category = null, ?int $minCapacity = null, ?int $maxCapacity = null): Collection
    {
        try {
            $query = Resource::query(); 

            // If a category is provided and it's not 'all' (or null, if 'all' is handled explicitly in frontend)
            if ($category && $category !== 'all') {
                $query->where('category', $category);
            }

            // Filter by minimum capacity
            if ($minCapacity !== null) {
                $query->where('capacity', '>=', $minCapacity);
            }
            // Filter by maximum capacity
            if ($maxCapacity !== null) {
                $query->where('capacity', '<=', $maxCapacity);
            }

            $query->orderBy('name');

            return $query->get(); 
        } catch (Throwable $e) {
            Log::error('Failed to fetch all resources: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new ResourceException('Could not retrieve resources.');
        }
    }

    /**
     * Create a new resource.
     *
     * @param array $data Validated data for resource creation.
     * @return Resource
     * @throws ResourceException
     */
    public function createResource(array $data): Resource
    {
       try {
            // Handle image upload if present
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = $data['image']->store('resources', 'public');
                $data['image'] = $imagePath;
            }

            return Resource::create($data);
        } catch (Throwable $e) {
            Log::debug('Failed to create resource: ' . $e->getMessage(), ['data' => $data, 'trace' => $e->getTraceAsString()]);
            throw new ResourceException('Could not create resource.');
        }
    }

    /**
     * Find a resource by its ID.
     *
     * @param int $resourceId
     * @return Resource
     * @throws ResourceException
     */
    public function getResourceById(int $resourceId): Resource
    {
        $resource = Resource::find($resourceId);

        if (!$resource) {
            throw new ResourceException('Resource not found.', 404);
        }

        return $resource;
    }

    /**
     * Update an existing resource.
     *
     * @param Resource $resource The resource instance to update.
     * @param array $data Validated data for resource update.
     * @return Resource
     * @throws ResourceException
     */
    public function updateResource(Resource $resource, array $data): Resource
    {
        try {
            // Handle image upload if present
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                // Delete old image if it exists
                if ($resource->image && \Storage::disk('public')->exists($resource->image)) {
                    \Storage::disk('public')->delete($resource->image);
                }
                
                $imagePath = $data['image']->store('resources', 'public');
                $data['image'] = $imagePath;
            }

            $resource->update($data);
            return $resource->fresh(); // Return the fresh instance from the database
        } catch (Throwable $e) {
            Log::error('Failed to update resource: ' . $e->getMessage(), ['resource_id' => $resource->id, 'data' => $data, 'trace' => $e->getTraceAsString()]);
            throw new ResourceException('Could not update resource.');
        }
    }

    /**
     * Delete a resource.
     *
     * @param Resource $resource The resource instance to delete.
     * @return bool True on successful deletion.
     * @throws ResourceException
     */
    public function deleteResource(Resource $resource): bool
    {
        try {
            // Check if the resource has any active bookings
            if ($resource->bookings()->where('status', '!=', 'cancelled')->exists()) {
                throw new ResourceException('Cannot delete resource with active bookings.');
            }

            // Delete the associated image if it exists
            if ($resource->image && \Storage::disk('public')->exists($resource->image)) {
                \Storage::disk('public')->delete($resource->image);
            }

            return $resource->delete();
        } catch (Throwable $e) {
            Log::error('Failed to delete resource: ' . $e->getMessage(), ['resource_id' => $resource->id, 'trace' => $e->getTraceAsString()]);
            throw new ResourceException('Could not delete resource.');
        }
    }

    /**
     * Get the most recently booked resources.
     *
     * @param int $limit
     * @return Collection<Resource>
     */
    public function getRecentlyBookedResources(int $limit = 5): Collection
    {
        // Get resources that have at least one booking, ordered by the latest booking's start_time
        return Resource::whereHas('bookings')
            ->with(['bookings' => function($query) {
                $query->orderByDesc('start_time');
            }])
            ->get()
            ->sortByDesc(function($resource) {
                return optional($resource->bookings->first())->start_time;
            })
            ->take($limit)
            ->values();
    }
}
