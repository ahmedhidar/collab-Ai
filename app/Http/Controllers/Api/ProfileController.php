<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\Profile\UploadAvatarRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\TaskChecklistResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    /**
     * Get the authenticated user's profile with stats and teams.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->profileService->getProfile($request->user());

        return $this->successResponse(
            data: new UserProfileResource($user),
            message: 'User profile retrieved successfully'
        );
    }

    /**
     * Update authenticated user personal info and experience settings.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile(
            $request->user(),
            $request->validated(),
            $request->ip()
        );

        return $this->successResponse(
            data: new UserProfileResource($user),
            message: 'User profile updated successfully'
        );
    }

    /**
     * Upload and update user avatar.
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->profileService->updateAvatar(
            $request->user(),
            $request->file('avatar'),
            $request->ip()
        );

        return $this->successResponse(
            data: new UserProfileResource($user),
            message: 'Avatar uploaded successfully'
        );
    }

    /**
     * Get user task checklist segregated into To-Do and Done.
     */
    public function tasks(Request $request): JsonResponse
    {
        $checklist = $this->profileService->getTaskChecklist($request->user());

        return $this->successResponse(
            data: new TaskChecklistResource($checklist),
            message: 'Task checklist retrieved successfully'
        );
    }

    /**
     * Quick toggle a task completion status.
     */
    public function toggleTask(Request $request, int $id): JsonResponse
    {
        $task = $this->profileService->toggleTask($request->user(), $id, $request->ip());

        return $this->successResponse(
            data: new TaskResource($task),
            message: 'Task status updated successfully'
        );
    }

    /**
     * Get user audit activity timeline.
     */
    public function activity(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $logs = $this->profileService->getActivityTimeline($request->user(), $limit);

        return $this->successResponse(
            data: ActivityLogResource::collection($logs),
            message: 'User activity timeline retrieved successfully'
        );
    }
}
