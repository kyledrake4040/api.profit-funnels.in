<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AutomationRequest;
use App\Models\Account;
use App\Models\Automation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Account-scoped automations (trigger → actions). Created together with their
 * ordered actions, like a pipeline with its stages.
 */
final class AutomationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $automations = $this->account($request)->automations()
            ->with('actions')
            ->latest()
            ->get();

        return $this->successResponse($automations);
    }

    public function store(AutomationRequest $request): JsonResponse
    {
        $account = $this->account($request);

        $automation = DB::transaction(function () use ($account, $request) {
            $automation = $account->automations()->create([
                'name'          => $request->input('name'),
                'trigger_event' => $request->input('trigger_event'),
                'is_active'     => $request->boolean('is_active', true),
            ]);

            foreach (array_values($request->input('actions', [])) as $i => $action) {
                $automation->actions()->create([
                    'type'       => $action['type'],
                    'config'     => $action['config'] ?? [],
                    'sort_order' => $i,
                ]);
            }

            return $automation;
        });

        return $this->successResponse($automation->load('actions'), __('Automation created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $automation = $this->resolve($request);

        if ($automation === null) {
            return $this->errorResponse(__('Automation not found.'), 404);
        }

        return $this->successResponse($automation->load('actions'));
    }

    public function update(Request $request): JsonResponse
    {
        $automation = $this->resolve($request);

        if ($automation === null) {
            return $this->errorResponse(__('Automation not found.'), 404);
        }

        $automation->fill($request->only(['name', 'is_active']));
        $automation->save();

        return $this->successResponse($automation->load('actions'), __('Automation updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $automation = $this->resolve($request);

        if ($automation === null) {
            return $this->errorResponse(__('Automation not found.'), 404);
        }

        $automation->delete();

        return $this->successResponse(null, __('Automation deleted.'));
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolve(Request $request): ?Automation
    {
        return $this->account($request)->automations()->find($request->route('automation'));
    }
}
