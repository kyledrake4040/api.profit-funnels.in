<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JobRequest;
use App\Models\Account;
use App\Models\ServiceJob;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Field-service jobs (work orders), nested under and scoped to an account. The
 * Jobber side of the platform: scheduled, billable work for a client. A linked
 * contact is verified to belong to the same account.
 */
final class JobController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $jobs = $this->account($request)->jobs()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with('contact:id,first_name,last_name')
            ->orderByRaw('scheduled_at is null, scheduled_at asc')
            ->get();

        return $this->successResponse($jobs);
    }

    public function store(JobRequest $request): JsonResponse
    {
        $account = $this->account($request);

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        $status = $request->input('status', config('custom.job.status_scheduled'));

        $job = $account->jobs()->create([
            'contact_id'   => $request->input('contact_id'),
            'title'        => $request->input('title'),
            'description'  => $request->input('description'),
            'status'       => $status,
            'scheduled_at' => $request->input('scheduled_at'),
            'completed_at' => $this->completedStamp($status, null),
            'value'        => $request->input('value', 0),
            'currency'     => $request->input('currency', config('funnel.currency', 'cad')),
            'address'      => $request->input('address'),
        ]);

        return $this->successResponse($job->load('contact'), __('Job created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $job = $this->resolve($request);

        if ($job === null) {
            return $this->errorResponse(__('Job not found.'), 404);
        }

        return $this->successResponse($job->load('contact'));
    }

    public function update(JobRequest $request): JsonResponse
    {
        $account = $this->account($request);
        $job     = $this->resolve($request);

        if ($job === null) {
            return $this->errorResponse(__('Job not found.'), 404);
        }

        if (($error = $this->validateContact($account, $request)) !== null) {
            return $error;
        }

        $status = $request->input('status', $job->status);

        $job->fill([
            'contact_id'   => $request->input('contact_id'),
            'title'        => $request->input('title'),
            'description'  => $request->input('description'),
            'status'       => $status,
            'scheduled_at' => $request->input('scheduled_at'),
            'completed_at' => $this->completedStamp($status, $job->completed_at),
            'value'        => $request->input('value', $job->value),
            'currency'     => $request->input('currency', $job->currency),
            'address'      => $request->input('address'),
        ]);
        $job->save();

        return $this->successResponse($job->load('contact'), __('Job updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $job = $this->resolve($request);

        if ($job === null) {
            return $this->errorResponse(__('Job not found.'), 404);
        }

        $job->delete();

        return $this->successResponse(null, __('Job deleted.'));
    }

    /**
     * Stamp completed_at the moment a job first becomes Completed; clear it if
     * the job moves back out of Completed.
     */
    private function completedStamp(string $status, ?Carbon $current): ?Carbon
    {
        if ($status === config('custom.job.status_completed')) {
            return $current ?? Carbon::now();
        }

        return null;
    }

    private function validateContact(Account $account, Request $request): ?JsonResponse
    {
        $contactId = $request->input('contact_id');

        if ($contactId !== null && ! $account->contacts()->whereKey($contactId)->exists()) {
            return $this->errorResponse(__('Contact not found in this account.'), 422);
        }

        return null;
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolve(Request $request): ?ServiceJob
    {
        return $this->account($request)->jobs()->find($request->route('job'));
    }
}
