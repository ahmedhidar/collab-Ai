<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Meeting\MeetingFilterRequest;
use App\Http\Requests\Api\Meeting\StoreMeetingRequest;
use App\Http\Requests\Api\Meeting\StoreTranscriptRequest;
use App\Http\Requests\Api\Meeting\UpdateAttendanceRequest;
use App\Http\Requests\Api\Meeting\UpdateMeetingRequest;
use App\Http\Requests\Api\Meeting\UpdateRsvpRequest;
use App\Http\Resources\AiMeetingSummaryResource;
use App\Http\Resources\MeetingDetailResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MeetingTranscriptResource;
use App\Services\MeetingAiService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService,
        protected MeetingAiService $meetingAiService
    ) {}

    /**
     * Display a listing of meetings for calendar and agenda views.
     */
    public function index(MeetingFilterRequest $request): JsonResponse
    {
        $meetings = $this->meetingService->list($request->user(), $request->validated());

        return $this->successResponse(
            data: MeetingResource::collection($meetings),
            message: 'Meetings retrieved successfully'
        );
    }

    /**
     * Store a newly created meeting.
     */
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $meeting = $this->meetingService->create($request->user(), $request->validated());

        return $this->successResponse(
            data: new MeetingDetailResource($meeting),
            message: 'Meeting scheduled successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified meeting details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('view', $meeting);

        return $this->successResponse(
            data: new MeetingDetailResource($meeting),
            message: 'Meeting details retrieved successfully'
        );
    }

    /**
     * Update the specified meeting.
     */
    public function update(UpdateMeetingRequest $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('update', $meeting);

        $updated = $this->meetingService->update($meeting, $request->validated());

        return $this->successResponse(
            data: new MeetingDetailResource($updated),
            message: 'Meeting updated successfully'
        );
    }

    /**
     * Cancel the specified meeting.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('update', $meeting);

        $cancelled = $this->meetingService->cancel($meeting);

        return $this->successResponse(
            data: new MeetingDetailResource($cancelled),
            message: 'Meeting cancelled successfully'
        );
    }

    /**
     * Remove the specified meeting from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('delete', $meeting);

        $this->meetingService->delete($meeting);

        return $this->successResponse(
            data: ['deleted' => true],
            message: 'Meeting deleted successfully'
        );
    }

    /**
     * RSVP to a meeting (accepted, declined, pending).
     */
    public function rsvp(UpdateRsvpRequest $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('rsvp', $meeting);

        $this->meetingService->updateRsvp($meeting, $request->user(), $request->validated('status'));
        $refreshed = $this->meetingService->find($id);

        return $this->successResponse(
            data: new MeetingDetailResource($refreshed),
            message: 'RSVP updated successfully'
        );
    }

    /**
     * Update attendance for meeting participants.
     */
    public function updateAttendance(UpdateAttendanceRequest $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('manageAttendance', $meeting);

        $this->meetingService->updateAttendance($meeting, $request->validated('attendances'));
        $refreshed = $this->meetingService->find($id);

        return $this->successResponse(
            data: new MeetingDetailResource($refreshed),
            message: 'Attendance updated successfully'
        );
    }

    /**
     * Upload or sync meeting transcripts.
     */
    public function storeTranscripts(StoreTranscriptRequest $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('storeTranscripts', $meeting);

        $transcripts = $this->meetingService->storeTranscripts(
            $meeting,
            $request->validated(),
            $request->file('file')
        );

        return $this->successResponse(
            data: MeetingTranscriptResource::collection($transcripts),
            message: 'Meeting transcripts saved successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Generate structured AI summary for the meeting using Gemini.
     */
    public function generateSummary(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingService->find($id);
        Gate::authorize('generateSummary', $meeting);

        try {
            $summary = $this->meetingAiService->generateSummary($meeting, $request->user());
        } catch (ValidationException $e) {
            return $this->errorResponse(
                message: 'Validation error',
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $e->errors()
            );
        }

        return $this->successResponse(
            data: new AiMeetingSummaryResource($summary),
            message: 'AI meeting summary generated successfully'
        );
    }
}
