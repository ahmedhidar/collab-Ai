<?php

namespace App\Http\Requests\Api\Chat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class SendProjectMessageRequest extends FormRequest
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
            'content' => ['nullable', 'string', 'max:10000'],
            'channel_name' => ['nullable', 'string', 'max:100'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'parent_message_id' => ['nullable', 'integer', 'exists:project_messages,id'],
            'attachment_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'attachment' => ['nullable', 'file', 'max:51200'],
            'mention_user_ids' => ['nullable', 'array'],
            'mention_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasContent = filled($this->input('content'));
            $hasAttachmentFileId = filled($this->input('attachment_file_id'));
            $hasUploadedFile = $this->hasFile('attachment');

            if (! $hasContent && ! $hasAttachmentFileId && ! $hasUploadedFile) {
                $validator->errors()->add('content', 'Message content or an attachment is required.');
            }
        });
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
