# CollabSpace Enterprise API Documentation

> **Version:** 2.1.0  
> **Environment:** PHP 8.4 / Laravel 12  
> **Authentication:** JSON Web Tokens (JWT) / Google OAuth 2.0  
> **Base URL:** `http://localhost:8000/api` (Local) | `https://api.collabspace.io/api` (Production)  
> **Standard Response Format:** JSON (`application/json`)

---

## Table of Contents
1. [Architectural Overview & Standards](#1-architectural-overview--standards)
2. [Authentication & Security](#2-authentication--security)
3. [Dashboard & Analytics](#3-dashboard--analytics)
4. [Teams Management](#4-teams-management)
5. [Projects Management](#5-projects-management)
6. [Tasks & Kanban Board](#6-tasks--kanban-board)
7. [Task Comments & Nested Threads](#7-task-comments--nested-threads)
8. [Real-Time Chat & Direct Messaging](#8-real-time-chat--direct-messaging)
9. [Meetings & AI Summaries](#9-meetings--ai-summaries)
10. [Reports & Deep Analytics](#10-reports--deep-analytics)
11. [Profile, Task Checklist & AI Assistant](#11-profile-task-checklist--ai-assistant)
12. [Real-Time WebSocket Channels](#12-real-time-websocket-channels)
13. [Error Handling & Status Codes](#13-error-handling--status-codes)

---

## 1. Architectural Overview & Standards

All endpoints adhere strictly to the **Layered Service Pattern**:
- **Controllers:** Thin orchestrators that delegate logic to domain services and return standardized JSON responses via `ApiResponseTrait`.
- **Form Requests:** Handle authorization, input sanitation, and validation rules.
- **API Resources:** Ensure predictable, versioned JSON responses with formatted datetime stamps and loaded relationships.
- **Standard Envelope:**
  ```json
  {
    "success": true,
    "message": "Operation completed successfully",
    "data": { ... }
  }
  ```

---

## 2. Authentication & Security

### 2.1 Register New User
Creates a new account and immediately returns a JWT authentication token.

- **Method:** `POST`
- **URL:** `/api/register`
- **Auth Required:** No

#### Request Body
| Field | Type | Required | Description |
|---|---|---|---|
| `full_name` | string | Yes | Full name of the user (max: 255) |
| `email` | string | Yes | Unique valid email address |
| `password` | string | Yes | Minimum 8 characters |
| `password_confirmation` | string | Yes | Must match `password` |
| `system_role` | string | No | `Admin`, `ProjectManager`, `TeamMember` (default: `TeamMember`) |
| `job_title` | string | No | Professional title (e.g., `Senior UI/UX Designer`) |
| `years_of_experience` | integer | No | Positive integer |

```json
{
  "full_name": "Alex Morgan",
  "email": "alex.morgan@collabspace.io",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "system_role": "TeamMember",
  "job_title": "Senior Frontend Engineer",
  "years_of_experience": 5
}
```

#### Response (`201 Created`)
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "Alex Morgan",
      "email": "alex.morgan@collabspace.io",
      "system_role": "TeamMember",
      "job_title": "Senior Frontend Engineer",
      "years_of_experience": 5,
      "availability_status": "Available",
      "avatar_url": null,
      "created_at": "2026-10-18T00:00:00Z"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

---

### 2.2 Login (Credentials)
Authenticates credentials and returns a bearer token.

- **Method:** `POST`
- **URL:** `/api/login`
- **Auth Required:** No

#### Request Body
```json
{
  "email": "alex.morgan@collabspace.io",
  "password": "SecurePassword123!"
}
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "full_name": "Alex Morgan",
      "email": "alex.morgan@collabspace.io",
      "system_role": "TeamMember"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

---

### 2.3 Get Current User Profile (`Me`)
Returns the authenticated user's model along with team affiliations and role capabilities.

- **Method:** `GET`
- **URL:** `/api/me`
- **Auth Required:** Yes (`Bearer <token>`)

#### Response (`200 OK`)
```json
{
  "success": true,
  "message": "Current user retrieved successfully",
  "data": {
    "id": 1,
    "full_name": "Alex Morgan",
    "email": "alex.morgan@collabspace.io",
    "system_role": "TeamMember",
    "job_title": "Senior Frontend Engineer",
    "availability_status": "Available",
    "teams": [
      {
        "id": 1,
        "name": "Frontend Engineering",
        "team_type": "Frontend"
      }
    ]
  }
}
```

---

### 2.4 Token Refresh & Logout
- **Refresh:** `POST /api/refresh` — Generates a new JWT token prior to expiration.
- **Logout:** `POST /api/logout` — Invalidates the token in the token blacklist.

---

### 2.5 Password Recovery Workflow
- `POST /api/forgot-password`: Dispatches `SendResetPasswordEmailJob` with password reset link.
- `POST /api/reset-password`: Accepts `token`, `email`, `password`, and `password_confirmation`.

---

### 2.6 Google OAuth 2.0 Authentication
- `GET /api/auth/google/redirect`: Returns authorization URL for Google Sign-In.
- `GET /api/auth/google/callback`: Redirect URI handler for web browser redirects.
- `POST /api/auth/google/token`: Accepts `id_token` from Mobile or SPA Google SDKs to exchange for CollabSpace JWT.

---

## 3. Dashboard & Analytics

### 3.1 Comprehensive Dashboard Overview
Aggregates key metrics, trend graphs, active projects, and upcoming meetings in a single query-optimized call.

- **Method:** `GET`
- **URL:** `/api/dashboard`
- **Auth Required:** Yes
- **Query Parameters:**
  - `period` (optional): `weekly`, `monthly`, `quarterly`, `yearly` (default: `monthly`)

#### Response (`200 OK`)
```json
{
  "success": true,
  "message": "Dashboard data retrieved successfully",
  "data": {
    "metrics": {
      "total_projects": 12,
      "active_projects": 8,
      "total_tasks": 145,
      "tasks_breakdown": {
        "pending": 25,
        "in_progress": 48,
        "in_review": 18,
        "completed": 54
      },
      "completion_rate": 37.24,
      "active_team_members": 24
    },
    "charts": {
      "completion_trend": {
        "labels": ["Week 1", "Week 2", "Week 3", "Week 4"],
        "completed": [12, 18, 14, 10],
        "created": [15, 20, 11, 8]
      }
    },
    "active_projects": [
      {
        "id": 1,
        "name": "CollabSpace Mobile App",
        "progress": 68,
        "end_date": "2027-01-15",
        "teams": ["Frontend", "UI/UX"]
      }
    ],
    "team_progress": [
      {
        "team_id": 1,
        "team_name": "UI/UX Team",
        "tasks_count": 35,
        "completed_count": 28,
        "rate": 80.0
      }
    ],
    "recent_files": [
      {
        "id": 10,
        "file_name": "design_system_v2.fig",
        "file_size": "14.2 MB",
        "uploaded_by": "Alex Morgan",
        "created_at": "2026-10-18T00:30:00Z"
      }
    ],
    "upcoming_meetings": [
      {
        "id": 4,
        "title": "Sprint 14 Review",
        "meeting_date": "2026-10-20",
        "start_time": "14:00",
        "meeting_link": "https://meet.google.com/xyz"
      }
    ]
  }
}
```

---

## 4. Teams Management

### 4.1 List & Manage Teams
- `GET /api/teams`: List all teams with member counts and active projects.
- `POST /api/teams`: Create team (`name`, `description`, `team_type`, `member_ids[]`).
- `GET /api/teams/{id}`: Detailed team roster with assigned projects.
- `PUT /api/teams/{id}`: Update team attributes.
- `DELETE /api/teams/{id}`: Remove team and detach associations.
- `POST /api/teams/{id}/members`: Add user to team (`user_id`).
- `DELETE /api/teams/{id}/members/{userId}`: Remove user from team.

---

## 5. Projects Management

### 5.1 Project Lifecycle Endpoints
- `GET /api/projects`: List projects with status and search filters (`status=Active|Inactive|Done`, `search=keywords`).
- `POST /api/projects`: Create project:
  ```json
  {
    "name": "Enterprise AI Assistant",
    "description": "Integration of LLM agents for workspace automation.",
    "start_date": "2026-11-01",
    "end_date": "2027-03-31",
    "status": "Active",
    "team_ids": [1, 2]
  }
  ```
- `GET /api/projects/{id}`: Retrieve project dossier with team cards and tasks summary.
- `PUT /api/projects/{id}`: Update project metadata.
- `DELETE /api/projects/{id}`: Cascade deletion of project workspace.
- `POST /api/projects/{id}/teams`: Allocate team (`team_id`).
- `DELETE /api/projects/{id}/teams/{teamId}`: Deallocate team.

---

## 6. Tasks & Kanban Board

### 6.1 Kanban Tasks List
- **Method:** `GET`
- **URL:** `/api/tasks`
- **Query Parameters:**
  - `project_id` (integer)
  - `status` (`pending`, `in_progress`, `in_review`, `completed`)
  - `priority` (`low`, `medium`, `high`)
  - `assignee_id` (integer)

#### Task Object Schema
```json
{
  "id": 1,
  "title": "Implement WebSockets Real-Time Channel",
  "description": "Broadcast MessageSent events on private project channels.",
  "status": "in_progress",
  "priority": "high",
  "progress": 60,
  "start_date": "2026-10-18",
  "due_date": "2026-10-25",
  "project": { "id": 1, "name": "CollabSpace Core" },
  "assignee": { "id": 2, "name": "Sarah Connor" },
  "reviewer": { "id": 3, "name": "John Doe" },
  "comments_count": 4,
  "attachments_count": 2
}
```

### 6.2 Update Task Status (Drag & Drop)
- **Method:** `PATCH`
- **URL:** `/api/tasks/{id}/status`
- **Body:**
  ```json
  {
    "status": "completed",
    "progress": 100
  }
  ```

---

## 7. Task Comments & Nested Threads

- `GET /api/tasks/{taskId}/comments`: Retrieve hierarchical nested comments tree.
- `POST /api/tasks/{taskId}/comments`: Post a comment or thread reply:
  ```json
  {
    "comment": "Verified the WebSocket connection latency: ~45ms.",
    "parent_id": null
  }
  ```
- `DELETE /api/tasks/{taskId}/comments/{commentId}`: Delete comment (author or Admin).

---

## 8. Real-Time Chat & Direct Messaging

### 8.1 Project Channels
- `GET /api/chats/sidebar`: Sidebar directory of channels and direct chats.
- `GET /api/chats/projects/{projectId}/channels`: List channels for project.
- `GET /api/chats/projects/{projectId}/messages`: Paginated messages with user avatars, attachments, and mentions (`?channel_name=general&per_page=25`).
- `POST /api/chats/projects/{projectId}/messages`: Send message with mentions:
  ```json
  {
    "channel_name": "general",
    "content": "Meeting in 15 minutes! @Alex Morgan",
    "mentioned_user_ids": [1],
    "parent_message_id": null
  }
  ```
- `POST /api/chats/projects/{projectId}/read`: Mark all channel messages read.

### 8.2 1-on-1 Direct Messaging (Inbox)
- `GET /api/inbox/threads`: List active inbox conversations with unread counter.
- `POST /api/inbox/threads`: Initialize conversation (`recipient_id`, `content`).
- `GET /api/inbox/threads/{threadId}/messages`: Fetch conversation history.
- `POST /api/inbox/threads/{threadId}/messages`: Send direct message (`content`, `parent_message_id`).
- `POST /api/inbox/threads/{threadId}/read`: Mark thread as read.

---

## 9. Meetings & AI Summaries

### 9.1 Meetings Endpoints
- `GET /api/meetings`: List scheduled and past meetings with filters.
- `POST /api/meetings`: Schedule meeting:
  ```json
  {
    "title": "Sprint Review & Gemini AI Demo",
    "description": "Demonstrating automated meeting transcript synthesis.",
    "meeting_date": "2026-10-22",
    "start_time": "15:00:00",
    "end_time": "16:00:00",
    "meeting_link": "https://meet.google.com/xyz-demo",
    "project_id": 1,
    "team_id": 1,
    "participant_ids": [1, 2, 3]
  }
  ```
- `POST /api/meetings/{id}/rsvp`: Update user attendance intent (`accepted`, `declined`, `pending`).
- `POST /api/meetings/{id}/attendance`: Record confirmed attendee checkins (`attendance: [{user_id: 1, attended: true}]`).
- `POST /api/meetings/{id}/transcripts`: Upload timestamped speaker lines:
  ```json
  {
    "transcripts": [
      {
        "speaker_id": 1,
        "speaker_name": "Alex Morgan",
        "time_offset": "00:05",
        "content": "Welcome everyone. Let us review the action items from last week."
      }
    ]
  }
  ```

### 9.2 AI Meeting Summary (Gemini 1.5 Flash)
- **Method:** `POST`
- **URL:** `/api/meetings/{id}/generate-summary`
- **Response (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "AI Meeting summary generated successfully",
    "data": {
      "summary_text": "The team reviewed sprint milestones and finalized the Gemini 1.5 API pipeline.",
      "key_points": [
        "Backend architecture meets all performance benchmarks",
        "WebSocket latency averages under 50ms"
      ],
      "decisions_made": [
        "Use Redis adapter for production broadcasting"
      ],
      "action_items": [
        "Alex: finalize Postman collection and publish API docs",
        "Sarah: verify Arabic transcript extraction"
      ],
      "risks_identified": [
        "Gemini API rate limiting on high-frequency bulk requests"
      ],
      "next_steps": "Deploy sprint release to staging on Thursday."
    }
  }
  ```

---

## 10. Reports & Deep Analytics

- `GET /api/reports/analytics`: Comprehensive KPIs (completion velocity, efficiency scores).
- `GET /api/reports/task-deadline-chart`: Timeseries comparing on-time vs overdue task resolutions.
- `GET /api/reports/projects-progress-chart`: Cross-project completion comparison.
- `GET /api/reports/worker-activity-chart`: Individual worker task load distribution.
- `POST /api/reports`: Generate custom snapshot report.
- `POST /api/reports/{id}/ai-insights`: Generate Gemini AI strategic recommendations based on metrics.

---

## 11. Profile, Task Checklist & AI Assistant

### 11.1 Profile & Tasks Checklist
- `GET /api/profile`: Authenticated user profile with bio and status.
- `PUT /api/profile`: Update details (`full_name`, `bio`, `job_title`, `availability_status`).
- `POST /api/profile/avatar`: Multipart file upload for profile image (`avatar`).
- `GET /api/profile/tasks`: Returns tasks divided into `todo` and `done` arrays matching Figma checklist.
- `PATCH /api/profile/tasks/{id}/toggle`: Toggle task between `completed` (100%) and `pending` (0%).
- `GET /api/profile/activity`: Audit trail of user actions across the platform.

### 11.2 Interactive AI Assistant (Gemini 1.5 Flash)
- **Method:** `POST`
- **URL:** `/api/ai/chat` (also aliased at `/api/ai/ask` and `/api/profile/ai-assistant`)
- **Body:**
  ```json
  {
    "prompt": "Summarize my high priority tasks and upcoming meetings for tomorrow."
  }
  ```
- **Response (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "AI Assistant response generated successfully",
    "data": {
      "query": "Summarize my high priority tasks and upcoming meetings for tomorrow.",
      "response": "You have 2 high-priority tasks due: 'Implement WebSockets' and 'Finalize Postman Collection'. You have 1 upcoming meeting: 'Sprint Review' scheduled for 15:00.",
      "tokens_used": 142,
      "execution_time_ms": 380
    }
  }
  ```
- `GET /api/ai/history`: Paginated log of past AI conversations.

---

## 12. Real-Time WebSocket Channels

Broadcasting is managed via Laravel Echo using Pusher / Laravel Reverb protocols with JWT Bearer authorization on `/api/broadcasting/auth`.

| Channel Name | Channel Type | Event | Payload |
|---|---|---|---|
| `private-project.{projectId}.{channelName}` | Private | `MessageSent` | `{ id, content, sender, attachment, replies, created_at }` |
| `private-inbox.{threadId}` | Private | `MessageSent` | `{ id, content, sender, thread_id, created_at }` |
| `private-user.{userId}` | Private | `NotificationSent` | `{ type, title, body, action_url, timestamp }` |

---

## 13. Error Handling & Status Codes

All errors return a standardized response format:
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

| HTTP Code | Error Type | Description |
|---|---|---|
| `400` | Bad Request | Malformed request or illegal state transition. |
| `401` | Unauthorized | Missing or expired JWT bearer token. |
| `403` | Forbidden | Insufficient user role permissions (Policy violation). |
| `404` | Not Found | Requested entity ID does not exist. |
| `422` | Unprocessable Entity | Validation errors on input parameters. |
| `500` | Server Error | Internal unhandled exception. |
