<?php

namespace App\Http\Requests\Api;

use App\Models\TradingSignal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTradingSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TradingSignal::class) === true;
    }

    public function rules(): array
    {
        $pricedSignal = fn (): bool => ! in_array($this->input('signal_type'), ['CLOSE', 'HOLD'], true);

        return [
            'symbol' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'market' => ['nullable', 'string', 'max:40'],
            'signal_type' => ['required', Rule::in(['BUY', 'SELL', 'LONG', 'SHORT', 'CLOSE', 'HOLD'])],
            'order_type' => ['nullable', Rule::in([
                'market_execution', 'buy_limit', 'sell_limit', 'buy_stop', 'sell_stop', 'pending_order',
            ])],
            'entry_price' => [Rule::requiredIf($pricedSignal), 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'entry_price_min' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'entry_price_max' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'stop_loss' => [Rule::requiredIf($pricedSignal), 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_1' => [Rule::requiredIf($pricedSignal), 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_2' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_3' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_4' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_5' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_6' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_7' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_8' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_9' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_10' => ['nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'risk_level' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'timeframe' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'strategy' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'chart_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'client_request_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('entry_price_min') && $this->filled('entry_price_max')
                && (float) $this->input('entry_price_min') > (float) $this->input('entry_price_max')) {
                $validator->errors()->add('entry_price_min', 'Entry price minimum must not exceed the maximum.');
            }

            $type = (string) $this->input('signal_type');
            if (! in_array($type, ['BUY', 'SELL', 'LONG', 'SHORT'], true)) {
                return;
            }

            $orderType = (string) $this->input('order_type', 'market_execution');
            $buy = in_array($type, ['BUY', 'LONG'], true);
            if (($buy && str_starts_with($orderType, 'sell_'))
                || (! $buy && str_starts_with($orderType, 'buy_'))) {
                $validator->errors()->add('order_type', 'The pending-order type must match the signal direction.');
            }

            $entry = (float) $this->input('entry_price');
            $stop = (float) $this->input('stop_loss');
            if (($buy && $stop >= $entry) || (! $buy && $stop <= $entry)) {
                $validator->errors()->add('stop_loss', $buy
                    ? 'For BUY/LONG signals, stop loss must be below entry.'
                    : 'For SELL/SHORT signals, stop loss must be above entry.');
            }

            $previous = null;
            for ($target = 1; $target <= 10; $target++) {
                $key = "take_profit_{$target}";
                if (! $this->filled($key)) {
                    continue;
                }
                $price = (float) $this->input($key);
                $invalidSide = $buy ? $price <= $entry : $price >= $entry;
                $invalidOrder = $previous !== null && ($buy ? $price <= $previous : $price >= $previous);
                if ($invalidSide || $invalidOrder) {
                    $validator->errors()->add($key, 'Take-profit targets must progress away from the entry price.');
                }
                $previous = $price;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'symbol' => strtoupper(trim((string) $this->input('symbol'))),
            'market' => strtoupper(trim((string) $this->input('market', ''))),
            'signal_type' => strtoupper(trim((string) $this->input('signal_type'))),
            'order_type' => $this->filled('order_type')
                ? strtolower(trim((string) $this->input('order_type')))
                : 'market_execution',
            'risk_level' => strtoupper(trim((string) $this->input('risk_level', ''))),
            'timeframe' => strtoupper(trim((string) $this->input('timeframe', ''))),
            'client_request_id' => $this->header('X-Idempotency-Key') ?: $this->input('client_request_id'),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to submit trading signals.',
            'error_code' => 'SIGNAL_SUBMIT_FORBIDDEN',
            'errors' => (object) [],
        ], 403));
    }
}
