<?php

namespace App\Http\Requests\Api\Meeting;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['Admin', 'ProjectManager', 'TeamMember']) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:txt,json,text/plain,application/json', 'max:5120'],
            'transcripts' => ['nullable', 'array'],
            'transcripts.*.speaker_id' => ['nullable', 'integer', 'exists:users,id'],
            'transcripts.*.speaker_name' => ['nullable', 'string', 'max:100'],
            'transcripts.*.time_offset' => ['nullable', 'string', 'max:20'],
            'transcripts.*.content' => ['required_with:transcripts', 'string'],
            'speaker_name' => ['nullable', 'string', 'max:100'],
            'time_offset' => ['nullable', 'string', 'max:20'],
            'content' => ['nullable', 'string'],
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
