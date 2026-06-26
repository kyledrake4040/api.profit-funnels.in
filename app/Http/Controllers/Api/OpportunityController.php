<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Automation\AutomationEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpportunityRequest;
use App\Models\Account;
use App\Models\Opportunity;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deals (opportunities) moving through an account's pipelines. Every referenced
 * pipeline, stage and contact is verified to belong to the same account, so a
 * request can never wire a deal to another tenant's records.
 */
final class OpportunityController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $opportunities = $this->account($request)->opportunities()
            ->when($request->query('pipeline_id'), fn ($q, $id) => $q->where('pipeline_id', $id))
            ->when($request->query('stage_id'), fn ($q, $id) => $q->where('stage_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['stage', 'contact'])
            ->latest()
            ->get();

        return $this->successResponse($opportunities);
    }

    public function store(OpportunityRequest $request): JsonResponse
    {
        $account = $this->account($request);

        $error = $this->validateOwnership($account, $request);
        if ($error !== null) {
            return $error;
        }

        $opportunity = $account->opportunities()->create([
            'pipeline_id'       => $request->input('pipeline_id'),
            'stage_id'          => $request->input('stage_id'),
            'contact_id'        => $request->input('contact_id'),
            'name'              => $request->input('name'),
            'value'             => $request->input('value', 0),
            'currency'          => $request->input('currency', config('funnel.currency', 'cad')),
            'status'            => $request->input('status', config('custom.opportunity.status_open')),
            'expected_close_at' => $request->input('expected_close_at'),
        ]);

        $this->fireIfWon($opportunity, $account, false);

        return $this->successResponse($opportunity->load(['stage', 'contact']), __('Opportunity created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $model = $this->resolve($request);

        if ($model === null) {
            return $this->errorResponse(__('Opportunity not found.'), 404);
        }

        return $this->successResponse($model->load(['pipeline', 'stage', 'contact']));
    }

    public function update(OpportunityRequest $request): JsonResponse
    {
        $account = $this->account($request);
        $model   = $this->resolve($request);

        if ($model === null) {
            return $this->errorResponse(__('Opportunity not found.'), 404);
        }

        $error = $this->validateOwnership($account, $request);
        if ($error !== null) {
            return $error;
        }

        $wasWon = $model->status === config('custom.opportunity.status_won');

        $model->fill([
            'pipeline_id'       => $request->input('pipeline_id'),
            'stage_id'          => $request->input('stage_id'),
            // Preserve the linked contact when the caller doesn't send one (e.g.
            // the board just moves a deal between stages).
            'contact_id'        => $request->input('contact_id', $model->contact_id),
            'name'              => $request->input('name'),
            'value'             => $request->input('value', $model->value),
            'currency'          => $request->input('currency', $model->currency),
            'status'            => $request->input('status', $model->status),
            'expected_close_at' => $request->input('expected_close_at'),
        ]);
        $model->save();

        $this->fireIfWon($model, $account, $wasWon);

        return $this->successResponse($model->load(['stage', 'contact']), __('Opportunity updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $model = $this->resolve($request);

        if ($model === null) {
            return $this->errorResponse(__('Opportunity not found.'), 404);
        }

        $model->delete();

        return $this->successResponse(null, __('Opportunity deleted.'));
    }

    /**
     * Fire "opportunity.won" automations the moment a deal first becomes Won
     * (not on every save of an already-won deal). The deal's contact, when set,
     * is the automation subject — enabling the GHL→Jobber bridge (won deal →
     * auto-create a job).
     */
    private function fireIfWon(Opportunity $opportunity, Account $account, bool $wasWon): void
    {
        if ($wasWon || $opportunity->status !== config('custom.opportunity.status_won')) {
            return;
        }

        if ($opportunity->contact_id === null) {
            return;
        }

        app(AutomationEngine::class)->fire(
            config('custom.automation.event_opportunity_won'),
            $account,
            ['contact' => $opportunity->contact],
        );
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolve(Request $request): ?Opportunity
    {
        return $this->account($request)->opportunities()->find($request->route('opportunity'));
    }

    /**
     * Ensure the referenced pipeline, stage and contact all belong to this
     * account (and the stage to the pipeline). Returns an error response to
     * short-circuit on, or null when everything checks out.
     */
    private function validateOwnership(Account $account, Request $request): ?JsonResponse
    {
        $pipeline = $account->pipelines()->find($request->input('pipeline_id'));
        if ($pipeline === null) {
            return $this->errorResponse(__('Pipeline not found in this account.'), 422);
        }

        if (! $pipeline->stages()->whereKey($request->input('stage_id'))->exists()) {
            return $this->errorResponse(__('Stage does not belong to the pipeline.'), 422);
        }

        $contactId = $request->input('contact_id');
        if ($contactId !== null && ! $account->contacts()->whereKey($contactId)->exists()) {
            return $this->errorResponse(__('Contact not found in this account.'), 422);
        }

        return null;
    }
}
