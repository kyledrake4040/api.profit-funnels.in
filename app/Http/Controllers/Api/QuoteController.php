<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteRequest;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Quote;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Price quotes (with line items) for a client. Accepted quotes convert into
 * invoices — the start of the get-paid loop. Account-scoped; a linked contact
 * is verified to belong to the account.
 */
final class QuoteController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $quotes = $this->account($request)->quotes()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['items', 'contact:id,first_name,last_name'])
            ->latest()
            ->get();

        return $this->successResponse($quotes);
    }

    public function store(QuoteRequest $request): JsonResponse
    {
        $account = $this->account($request);

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        $quote = DB::transaction(function () use ($account, $request) {
            $quote = $account->quotes()->create([
                'contact_id' => $request->input('contact_id'),
                'job_id'     => $request->input('job_id'),
                'number'     => $this->nextNumber($account),
                'status'     => config('custom.quote.status_draft'),
                'currency'   => $request->input('currency', config('funnel.currency', 'cad')),
                'notes'      => $request->input('notes'),
            ]);

            $this->syncItems($quote, $request->input('items', []));
            $quote->recalculateTotal();

            return $quote;
        });

        return $this->successResponse($quote->load(['items', 'contact']), __('Quote created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $quote = $this->resolve($request);

        if ($quote === null) {
            return $this->errorResponse(__('Quote not found.'), 404);
        }

        return $this->successResponse($quote->load(['items', 'contact']));
    }

    public function update(QuoteRequest $request): JsonResponse
    {
        $account = $this->account($request);
        $quote   = $this->resolve($request);

        if ($quote === null) {
            return $this->errorResponse(__('Quote not found.'), 404);
        }

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        DB::transaction(function () use ($quote, $request) {
            $quote->fill([
                'contact_id' => $request->input('contact_id'),
                'currency'   => $request->input('currency', $quote->currency),
                'notes'      => $request->input('notes'),
            ])->save();

            $quote->items()->delete();
            $this->syncItems($quote, $request->input('items', []));
            $quote->recalculateTotal();
        });

        return $this->successResponse($quote->load(['items', 'contact']), __('Quote updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $quote = $this->resolve($request);

        if ($quote === null) {
            return $this->errorResponse(__('Quote not found.'), 404);
        }

        $quote->delete();

        return $this->successResponse(null, __('Quote deleted.'));
    }

    public function accept(Request $request): JsonResponse
    {
        $quote = $this->resolve($request);

        if ($quote === null) {
            return $this->errorResponse(__('Quote not found.'), 404);
        }

        $quote->status = config('custom.quote.status_accepted');
        $quote->save();

        return $this->successResponse($quote->load('items'), __('Quote accepted.'));
    }

    /**
     * Convert a quote into a Draft invoice, copying its line items, and mark the
     * quote Accepted.
     */
    public function convert(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $quote   = $this->resolve($request);

        if ($quote === null) {
            return $this->errorResponse(__('Quote not found.'), 404);
        }

        $invoice = DB::transaction(function () use ($account, $quote) {
            $invoice = $account->invoices()->create([
                'contact_id' => $quote->contact_id,
                'quote_id'   => $quote->id,
                'number'     => $this->nextInvoiceNumber($account),
                'status'     => config('custom.invoice.status_draft'),
                'currency'   => $quote->currency,
                'notes'      => $quote->notes,
            ]);

            foreach ($quote->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                ]);
            }
            $invoice->recalculateTotal();

            $quote->status = config('custom.quote.status_accepted');
            $quote->save();

            return $invoice;
        });

        return $this->successResponse($invoice->load('items'), __('Quote converted to invoice.'), 201);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(Quote $quote, array $items): void
    {
        foreach (array_values($items) as $item) {
            $quote->items()->create([
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
        return 'Q-' . str_pad((string) ($account->quotes()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextInvoiceNumber(Account $account): string
    {
        return 'INV-' . str_pad((string) ($account->invoices()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolve(Request $request): ?Quote
    {
        return $this->account($request)->quotes()->find($request->route('quote'));
    }
}
