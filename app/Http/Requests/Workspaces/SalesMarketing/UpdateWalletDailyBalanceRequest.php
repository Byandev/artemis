<?php

namespace App\Http\Requests\Workspaces\SalesMarketing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One cell of the Go Tyme Balance grid: the balance that day should close at.
 * It is not stored as a figure — the controller posts a correction to the
 * wallet's ledger for the difference, so the grid stays derived. A null balance
 * drops that correction and hands the day back to the entries alone.
 *
 * Days after today are not editable: there is no ledger to correct yet.
 *
 * Authorization is handled by the controller/gate; the request only validates.
 */
class UpdateWalletDailyBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'balance' => ['present', 'nullable', 'numeric'],
        ];
    }
}
