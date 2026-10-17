<?php

namespace App\Http\Requests\Api\Chat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class SendInboxMessageRequest extends FormRequest
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
            'parent_message_id' => ['nullable', 'integer', 'exists:inbox_messages,id'],
            'attachment_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'attachment' => ['nullable', 'file', 'max:51200'],
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
