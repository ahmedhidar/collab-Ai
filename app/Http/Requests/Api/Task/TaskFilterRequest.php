<?php

namespace App\Http\Requests\Api\Task;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class TaskFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:Pending,InProgress,InReview,Completed'],
            'priority' => ['nullable', 'string', 'in:Low,Medium,High,Critical'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'my_tasks' => ['nullable', 'boolean'],
            'kanban' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
