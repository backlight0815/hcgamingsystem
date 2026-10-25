<?php

namespace App\Http\Requests\Api;

use App\Models\TradingSignal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTradingSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $signal = $this->route('signal');

        return $signal instanceof TradingSignal && $this->user()?->can('update', $signal) === true;
    }

    public function rules(): array
    {
        return [
            'symbol' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'market' => ['sometimes', 'nullable', 'string', 'max:40'],
            'signal_type' => ['sometimes', 'required', Rule::in(['BUY', 'SELL', 'LONG', 'SHORT', 'CLOSE', 'HOLD'])],
            'order_type' => ['sometimes', 'required', Rule::in([
                'market_execution', 'buy_limit', 'sell_limit', 'buy_stop', 'sell_stop', 'pending_order',
            ])],
            'entry_price' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,8'],
            'entry_price_min' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'entry_price_max' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'stop_loss' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_1' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_2' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_3' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_4' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_5' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_6' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_7' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_8' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_9' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'take_profit_10' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,8'],
            'risk_level' => ['sometimes', 'nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'timeframe' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'strategy' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'link' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'chart_image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'expected_version' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $signal = $this->route('signal');
            if (! $signal instanceof TradingSignal) {
                return;
            }

            $entryMin = $this->exists('entry_price_min')
                ? $this->input('entry_price_min')
                : $signal->entry_price_min;
            $entryMax = $this->exists('entry_price_max')
                ? $this->input('entry_price_max')
                : $signal->entry_price_max;
            if (filled($entryMin) && filled($entryMax) && (float) $entryMin > (float) $entryMax) {
                $validator->errors()->add('entry_price_min', 'Entry price minimum must not exceed the maximum.');
            }

            $type = (string) $this->input('signal_type', $signal->immediate_action);
            if (! in_array($type, ['BUY', 'SELL', 'LONG', 'SHORT'], true)) {
                return;
            }

            $orderType = (string) $this->input('order_type', $signal->order_type ?: 'market_execution');
            $buy = in_array($type, ['BUY', 'LONG'], true);
            if (($buy && str_starts_with($orderType, 'sell_'))
                || (! $buy && str_starts_with($orderType, 'buy_'))) {
                $validator->errors()->add('order_type', 'The pending-order type must match the signal direction.');
            }

            $entry = (float) $this->input('entry_price', $signal->entry_price);
            $stop = (float) $this->input('stop_loss', $signal->stop_loss);
            if (($buy && $stop >= $entry) || (! $buy && $stop <= $entry)) {
                $validator->errors()->add('stop_loss', $buy
                    ? 'For BUY/LONG signals, stop loss must be below entry.'
                    : 'For SELL/SHORT signals, stop loss must be above entry.');
            }

            $previous = null;
            for ($target = 1; $target <= 10; $target++) {
                $key = "take_profit_{$target}";
                $value = $this->exists($key) ? $this->input($key) : $signal->{"target_{$target}"};
                if (blank($value)) {
                    continue;
                }
                $price = (float) $value;
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
        foreach (['symbol', 'market', 'signal_type', 'risk_level', 'timeframe'] as $key) {
            if ($this->exists($key) && is_string($this->input($key))) {
                $this->merge([$key => strtoupper(trim($this->input($key)))]);
            }
        }
        if ($this->exists('order_type') && is_string($this->input('order_type'))) {
            $this->merge(['order_type' => strtolower(trim($this->input('order_type')))]);
        }
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
            'message' => 'You do not have permission to update this signal.',
            'error_code' => 'SIGNAL_UPDATE_FORBIDDEN',
            'errors' => (object) [],
        ], 403));
    }
}
