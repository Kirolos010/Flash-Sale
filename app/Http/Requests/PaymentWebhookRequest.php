<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string',
            'status' => 'required|in:success,failed',
            'order_id' => 'nullable|exists:orders,id',
            'payload' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Idempotency key is required',
            'idempotency_key.string' => 'Idempotency key must be a string',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either success or failed',
            'order_id.exists' => 'Order not found',
            'payload.array' => 'Payload must be an array',
        ];
    }
}


