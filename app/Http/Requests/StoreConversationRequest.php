<?php

namespace App\Http\Requests;

use App\Models\Requisition;
use App\Services\MessagingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $allowed = app(MessagingService::class)->contactsFor($this->user())->modelKeys();

        return [
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['integer', 'distinct', Rule::in($allowed)],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'requisition_id' => ['nullable', 'integer', 'exists:requisitions,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $requisition = $this->requisition();
                if ($requisition && $this->user()->cannot('view', $requisition)) {
                    $validator->errors()->add('requisition_id', 'You can only link a requisition you have access to.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'Choose at least one recipient.',
            'recipients.max' => 'A conversation can have at most 10 recipients.',
            'recipients.*.in' => 'You can only message the supply office administrators.',
        ];
    }

    public function requisition(): ?Requisition
    {
        return $this->filled('requisition_id') ? Requisition::find($this->integer('requisition_id')) : null;
    }
}
