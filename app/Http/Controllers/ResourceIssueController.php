<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use App\Models\ResourceIssue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResourceIssueController extends Controller
{
    /**
     * Display a listing of the resource issues (Admin/FM only).
     */
    public function index()
    {
        // Gate::authorize('manage-issues'); // Uncomment when ready
        $user = Auth::user();
        if($user->user_type == 'admin'){
            $issues = ResourceIssue::with(['resource', 'reporter', 'resolver'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        }else{
            $issues = ResourceIssue::with(['resource', 'reporter', 'resolver'])
                ->where('reported_by_user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return response()->json($issues);
    }

    /**
     * Store a newly created resource issue.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Improved validation with better error messages
        $validated = $request->validate([
            'name' => ['required', 'exists:resources,name'],
            'subject' => ['required', 'string', 'max:255', 'min:3'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,png,jpg,gif'], 
        ], [
            'resource_id.required' => 'Resource ID is required.',
            'resource_id.exists' => 'The selected resource does not exist.',
            'subject.required' => 'Subject is required.',
            'subject.min' => 'Subject must be at least 3 characters long.',
            'photo.image' => 'The uploaded file must be an image.',
            'photo.max' => 'The image size must not exceed 2MB.',
            'photo.mimes' => 'Only JPEG, PNG, JPG, and GIF images are allowed.',
        ]);

        // Handle null string values for description
        if (isset($validated['description']) && $validated['description'] === 'null') {
            $validated['description'] = null;
        }

        $resource = Resource::where('name', $validated['name'])->first();
        if (!$resource) {
            throw ValidationException::withMessages(['name' => 'The selected resource does not exist.']);
        }
        $validated['resource_id'] = $resource->id;

        $photoPath = null;
        try {
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                
                // Validate file is actually uploaded
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'File upload failed. Please try again.',
                        'error' => 'Invalid file upload'
                    ], 400);
                }

                // Generate unique filename
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $photoPath = $file->storeAs('issue_photos', $filename, 'public');
                
                // Verify file was actually stored
                if (!Storage::disk('public')->exists($photoPath)) {
                    throw new \Exception('Failed to store uploaded file');
                }
            }

            $issue = ResourceIssue::create([
                 'reported_by_user_id' => $user->id, 
                'resource_id' => $validated['resource_id'],
               
                'subject' => $validated['subject'],
                'description' => $validated['description'] ?? null,
                'photo_path' => $photoPath,
                'status' => 'reported',
            ]);

            return response()->json([
                'message' => 'Issue reported successfully!',
                'issue' => $issue->load(['resource', 'reporter'])
            ], 201);

        } catch (\Exception $e) {
            // Clean up uploaded file if database save fails
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            
            // Log the error for debugging
            \Log::error('ResourceIssue creation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'resource_id' => $validated['resource_id'] ?? null,
                'photo_path' => $photoPath,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to report issue. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource issue (Admin/FM only).
     */
    public function update(Request $request, ResourceIssue $issue)
    {
        // Gate::authorize('manage-issues'); // Uncomment when ready
        $user = Auth::user();

        $validated = $request->validate([
            'status' => ['required', 'in:reported,in_progress,resolved,wont_fix'],
            'resolved_by_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $issue->status = $validated['status'];
        
        if ($validated['status'] === 'resolved' && !$issue->resolved_at) {
            $issue->resolved_at = now();
            $issue->resolved_by_user_id = $user->id;
        } elseif ($validated['status'] !== 'resolved' && $issue->resolved_at) {
            $issue->resolved_at = null;
            $issue->resolved_by_user_id = null;
        }
        
        $issue->save();

        return response()->json([
            'message' => 'Issue updated successfully!',
            'issue' => $issue->load(['resource', 'reporter', 'resolver'])
        ]);
    }

    /**
     * Display the specified resource issue.
     */
    public function show(ResourceIssue $issue)
    {
        $user = Auth::user();
        
        // Check if user can view this issue
        if ($user->user_type !== 'admin' && $issue->reported_by_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'issue' => $issue->load(['resource', 'reporter', 'resolver'])
        ]);
    }

    /**
     * Serve the photo for a resource issue.
     */
    public function servePhoto(ResourceIssue $issue)
    {
        if (!$issue->photo_path) {
            return response()->json(['message' => 'No photo available'], 404);
        }

        if (!Storage::disk('public')->exists($issue->photo_path)) {
            return response()->json(['message' => 'Photo file not found'], 404);
        }

        return Storage::disk('public')->response($issue->photo_path);
    }

    /**
     * Remove the specified resource issue from storage (Admin/FM only).
     */
    public function destroy(ResourceIssue $issue)
    {
        //Gate::authorize('manage-issues');

        try {
            if ($issue->photo_path) {
                Storage::disk('public')->delete($issue->photo_path);
            }
            $issue->delete();

            return response()->json(['message' => 'Issue deleted successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete issue.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Issue Summary Report: Filters by date range, issue type, and status. Returns total issues, issues by type, and average resolution time.
     *
     * Query params: start_date, end_date, issue_type, status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function issueSummaryReport(Request $request)
    {
        $query = ResourceIssue::query();

        // Filters
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('issue_type')) {
            $query->where('issue_type', $request->input('issue_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $issues = $query->get();

        // Metrics
        $totalIssues = $issues->count();
        $issuesByType = $issues->groupBy('issue_type')->map->count();
        $avgResolutionTime = $issues->whereNotNull('resolved_at')->avg(function ($issue) {
            return $issue->created_at->diffInSeconds($issue->resolved_at);
        });
        $avgResolutionTime = $avgResolutionTime ? round($avgResolutionTime / 3600, 2) : null; // hours

        return response()->json([
            'success' => true,
            'filters' => [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'issue_type' => $request->input('issue_type'),
                'status' => $request->input('status'),
            ],
            'metrics' => [
                'total_issues' => $totalIssues,
                'issues_by_type' => $issuesByType,
                'average_resolution_time_hours' => $avgResolutionTime,
            ],
        ]);
    }
}

