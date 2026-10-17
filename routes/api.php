<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectChatController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Guest)
|--------------------------------------------------------------------------
*/
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

Route::controller(ForgotPasswordController::class)->group(function () {
    Route::post('forgot-password', 'sendResetLink');
    Route::post('reset-password', 'resetPassword');
});

Route::controller(GoogleAuthController::class)->prefix('auth/google')->group(function () {
    Route::get('redirect', 'getRedirectUrl');
    Route::get('callback', 'handleWebCallback');
    Route::post('token', 'loginWithToken');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires valid JWT Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::get('me', 'me');
        Route::post('refresh', 'refresh');
        Route::post('logout', 'logout');
    });

    // Dashboard & Analytics
    Route::controller(DashboardController::class)->prefix('dashboard')->group(function () {
        Route::get('/', 'index');
        Route::get('stats', 'stats');
        Route::get('chart', 'chart');
        Route::get('active-projects', 'activeProjects');
        Route::get('team-progress', 'teamProgress');
        Route::get('recent-files', 'recentFiles');
        Route::get('upcoming-meetings', 'upcomingMeetings');
    });

    // Teams Management
    Route::controller(TeamController::class)->prefix('teams')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::put('{team}', 'update');
        Route::delete('{team}', 'destroy');
        Route::post('{team}/members', 'addMember');
        Route::delete('{team}/members/{userId}', 'removeMember');
    });

    // Projects Management
    Route::controller(ProjectController::class)->prefix('projects')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::put('{project}', 'update');
        Route::delete('{project}', 'destroy');
        Route::post('{project}/teams', 'assignTeam');
        Route::delete('{project}/teams/{teamId}', 'removeTeam');
    });

    // Tasks & Kanban Management
    Route::controller(TaskController::class)->prefix('tasks')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::put('{task}', 'update');
        Route::patch('{task}/status', 'updateStatus');
        Route::delete('{task}', 'destroy');
    });

    // Task Comments (Nested)
    Route::controller(TaskCommentController::class)->prefix('tasks/{taskId}/comments')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('{commentId}', 'destroy');
    });

    // Project Chat Channels
    Route::controller(ProjectChatController::class)->prefix('chats')->group(function () {
        Route::get('sidebar', 'sidebar');
        Route::get('projects/{projectId}/channels', 'channels');
        Route::get('projects/{projectId}/messages', 'messages');
        Route::post('projects/{projectId}/messages', 'sendMessage');
        Route::post('projects/{projectId}/read', 'markRead');
    });

    // 1-on-1 Direct Messaging (Inbox)
    Route::controller(InboxController::class)->prefix('inbox')->group(function () {
        Route::get('threads', 'threads');
        Route::post('threads', 'startThread');
        Route::get('threads/{threadId}/messages', 'messages');
        Route::post('threads/{threadId}/messages', 'sendMessage');
        Route::post('threads/{threadId}/read', 'markRead');
    });

    // Meetings Management & AI Summaries
    Route::controller(MeetingController::class)->prefix('meetings')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::put('{id}', 'update');
        Route::patch('{id}/cancel', 'cancel');
        Route::delete('{id}', 'destroy');
        Route::post('{id}/rsvp', 'rsvp');
        Route::post('{id}/attendance', 'updateAttendance');
        Route::post('{id}/transcripts', 'storeTranscripts');
        Route::post('{id}/generate-summary', 'generateSummary');
        Route::post('{id}/ai-summary', 'generateSummary');
    });

    // Reports & Analytics
    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('analytics', 'analytics');
        Route::get('task-deadline-chart', 'taskDeadlineChart');
        Route::get('projects-progress-chart', 'projectsProgressChart');
        Route::get('worker-activity-chart', 'workerActivityChart');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('{id}', 'show');
        Route::delete('{id}', 'destroy');
        Route::post('{id}/ai-insights', 'generateAiInsights');
    });

    // User Profile & Activity Timeline
    Route::controller(ProfileController::class)->prefix('profile')->group(function () {
        Route::get('/', 'show');
        Route::put('/', 'update');
        Route::post('avatar', 'uploadAvatar');
        Route::get('tasks', 'tasks');
        Route::patch('tasks/{id}/toggle', 'toggleTask');
        Route::get('activity', 'activity');
    });

    // CollabSpace AI Assistant
    Route::controller(AiAssistantController::class)->prefix('ai')->group(function () {
        Route::post('chat', 'chat');
        Route::post('ask', 'chat');
        Route::get('history', 'history');
    });
    Route::post('profile/ai-assistant', [AiAssistantController::class, 'chat']);
});
