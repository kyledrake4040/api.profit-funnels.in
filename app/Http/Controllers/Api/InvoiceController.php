<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceRequest;
use App\Models\Account;
use App\Models\Invoice;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Client invoices (with line items). Closes the get-paid loop: mark an invoice
 * Paid and its total flows into the account's revenue. Account-scoped.
 */
final class InvoiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $invoices = $this->account($request)->invoices()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['items', 'contact:id,first_name,last_name'])
            ->latest()
            ->get();

        return $this->successResponse($invoices);
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $account = $this->account($request);

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        $invoice = DB::transaction(function () use ($account, $request) {
            $invoice = $account->invoices()->create([
                'contact_id' => $request->input('contact_id'),
                'number'     => $this->nextNumber($account),
                'status'     => config('custom.invoice.status_draft'),
                'currency'   => $request->input('currency', config('funnel.currency', 'cad')),
                'due_at'     => $request->input('due_at'),
                'notes'      => $request->input('notes'),
            ]);

            $this->syncItems($invoice, $request->input('items', []));
            $invoice->recalculateTotal();

            return $invoice;
        });

        return $this->successResponse($invoice->load(['items', 'contact']), __('Invoice created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $invoice = $this->resolve($request);

        if ($invoice === null) {
            return $this->errorResponse(__('Invoice not found.'), 404);
        }

        return $this->successResponse($invoice->load(['items', 'contact']));
    }

    public function update(InvoiceRequest $request): JsonResponse
    {
        $account = $this->account($request);
        $invoice = $this->resolve($request);

        if ($invoice === null) {
            return $this->errorResponse(__('Invoice not found.'), 404);
        }

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        DB::transaction(function () use ($invoice, $request) {
            $invoice->fill([
                'contact_id' => $request->input('contact_id'),
                'currency'   => $request->input('currency', $invoice->currency),
                'due_at'     => $request->input('due_at'),
                'notes'      => $request->input('notes'),
            ])->save();

            $invoice->items()->delete();
            $this->syncItems($invoice, $request->input('items', []));
            $invoice->recalculateTotal();
        });

        return $this->successResponse($invoice->load(['items', 'contact']), __('Invoice updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $invoice = $this->resolve($request);

        if ($invoice === null) {
            return $this->errorResponse(__('Invoice not found.'), 404);
        }

        $invoice->delete();

        return $this->successResponse(null, __('Invoice deleted.'));
    }

    /**
     * Mark an invoice paid (idempotent — re-paying a paid invoice is a no-op).
     */
    public function pay(Request $request): JsonResponse
    {
        $invoice = $this->resolve($request);

        if ($invoice === null) {
            return $this->errorResponse(__('Invoice not found.'), 404);
        }

        if (! $invoice->isPaid()) {
            $invoice->status  = config('custom.invoice.status_paid');
            $invoice->paid_at = Carbon::now();
            $invoice->save();
        }

        return $this->successResponse($invoice->load('items'), __('Invoice marked paid.'));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'] ?? 1,
                'unit_price'  => $item['unit_price'] ?? 0,
            ]);
        }
    }

    private function validateContact(Account $account, Request $request): ?JsonResponse
    {
        $contactId = $request->input('contact_id');

        if ($contactId !== null && ! $account->contacts()->whereKey($contactId)->exists()) {
            return $this->errorResponse(__('Contact not found in this account.'), 422);
        }

        return null;
    }

    private function nextNumber(Account $account): string
    {
        return 'INV-' . str_pad((string) ($account->invoices()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolve(Request $request): ?Invoice
    {
        return $this->account($request)->invoices()->find($request->route('invoice'));
    }
}
