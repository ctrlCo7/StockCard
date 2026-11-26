<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Recipients typed into a "Send email" dialog: one or more addresses separated by commas, semicolons or spaces. */
class SendNotificationEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('notifications.send');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipients' => collect(preg_split('/[\s,;]+/', (string) $this->input('emails')))
                ->filter()->map(fn (string $email) => strtolower($email))->unique()->values()->all(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'string', 'max:1000'],
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['email'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emails.required' => 'Enter at least one email address.',
            'recipients.max' => 'You can send to at most 10 addresses at a time.',
            'recipients.*.email' => '":input" is not a valid email address.',
        ];
    }

    /** @return array<int, string> */
    public function recipients(): array
    {
        return $this->validated('recipients');
    }
}
