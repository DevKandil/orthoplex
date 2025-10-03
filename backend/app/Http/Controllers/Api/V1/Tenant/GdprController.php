<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\GdprDeleteRequest;
use App\Models\GdprExport;
use App\Models\User;
use App\Jobs\ProcessGdprExport;
use App\Jobs\ProcessGdprDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GdprController extends Controller
{

    /**
     * Request data export for the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/gdpr/export",
     *     tags={"GDPR"},
     *     summary="Request data export",
     *     description="Request a complete export of user data for GDPR compliance",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="format", type="string", enum={"json", "csv"}, default="json"),
     *             @OA\Property(property="include_deleted", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Export request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Data export request created"),
     *             @OA\Property(property="export_id", type="string", example="exp_abc123"),
     *             @OA\Property(property="estimated_completion", type="string", format="datetime")
     *         )
     *     ),
     *     @OA\Response(response=429, description="Too many export requests")
     * )
     */
    public function requestExport(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'format' => 'string|in:json,csv',
            'include_deleted' => 'boolean'
        ]);

        // Check for existing pending exports
        $pendingExports = GdprExport::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        if ($pendingExports > 0) {
            return response()->json([
                'error' => 'Export already in progress',
                'message' => 'You already have a pending data export request.'
            ], 429);
        }

        // Create export request
        $export = GdprExport::create([
            'user_id' => $user->id,
            'export_id' => 'exp_' . Str::random(16),
            'email' => $user->email,
            'format' => $request->get('format', 'json'),
            'include_deleted' => $request->boolean('include_deleted'),
            'status' => 'pending',
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // Dispatch job to process export
        ProcessGdprExport::dispatch($export)->delay(now()->addMinutes(2));

        return response()->json([
            'message' => 'Data export request created successfully',
            'export_id' => $export->export_id,
            'estimated_completion' => now()->addMinutes(15)->toISOString()
        ]);
    }

    /**
     * Get status of data export.
     *
     * @OA\Get(
     *     path="/api/v1/gdpr/export/{exportId}",
     *     tags={"GDPR"},
     *     summary="Get export status",
     *     description="Check the status of a data export request",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="exportId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Export status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}),
     *             @OA\Property(property="download_url", type="string", nullable=true),
     *             @OA\Property(property="expires_at", type="string", format="datetime"),
     *             @OA\Property(property="file_size", type="integer", nullable=true)
     *         )
     *     )
     * )
     */
    public function getExportStatus(Request $request, string $exportId): JsonResponse
    {
        $user = $request->user();

        $export = GdprExport::where('user_id', $user->id)
            ->where('export_id', $exportId)
            ->firstOrFail();

        $response = [
            'status' => $export->status,
            'requested_at' => $export->requested_at,
            'expires_at' => $export->expires_at,
        ];

        if ($export->status === 'completed' && $export->file_path) {
            $response['download_url'] = route('api.v1.tenant.gdpr.download', $exportId);
            $response['file_size'] = $export->file_size;
        }

        if ($export->status === 'failed') {
            $response['error'] = $export->error_message;
        }

        return response()->json($response);
    }

    /**
     * Download exported data.
     *
     * @OA\Get(
     *     path="/api/v1/gdpr/download/{exportId}",
     *     tags={"GDPR"},
     *     summary="Download exported data",
     *     description="Download the exported data file",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="exportId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="File download"),
     *     @OA\Response(response=404, description="Export not found or expired")
     * )
     */
    public function downloadExport(Request $request, string $exportId): JsonResponse|StreamedResponse
    {
        $user = $request->user();

        $export = GdprExport::where('user_id', $user->id)
            ->where('export_id', $exportId)
            ->where('status', 'completed')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        if (!$export->file_path || !Storage::disk('local')->exists($export->file_path)) {
            return response()->json(['error' => 'Export file not found'], 404);
        }

        return Storage::disk('local')->download(
            $export->file_path,
            'data-export-' . now()->format('Y-m-d') . '.' . $export->format
        );
    }

    /**
     * Request account deletion.
     *
     * @OA\Post(
     *     path="/api/v1/gdpr/delete",
     *     tags={"GDPR"},
     *     summary="Request account deletion",
     *     description="Request deletion of user account and all associated data",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "confirmation"},
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="confirmation", type="string", example="DELETE MY ACCOUNT"),
     *             @OA\Property(property="reason", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion request created",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="request_id", type="string"),
     *             @OA\Property(property="scheduled_deletion", type="string", format="datetime")
     *         )
     *     )
     * )
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE MY ACCOUNT',
            'reason' => 'string|nullable|max:500'
        ]);

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
                'message' => 'The provided password is incorrect.'
            ], 422);
        }

        // Check if user can be deleted
        if (!$user->canBeDeleted()) {
            return response()->json([
                'error' => 'Account cannot be deleted',
                'message' => 'Your account cannot be deleted due to business constraints.'
            ], 422);
        }

        // Check for existing deletion request
        $existingRequest = GdprDeleteRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'error' => 'Deletion already requested',
                'message' => 'You already have a pending deletion request.',
                'scheduled_deletion' => $existingRequest->scheduled_deletion_at
            ], 429);
        }

        $deletionRequest = GdprDeleteRequest::create([
            'user_id' => $user->id,
            'request_id' => 'del_' . Str::random(16),
            'email' => $user->email,
            'reason' => $request->reason,
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_deletion_at' => now()->addDays(30),
        ]);

        // Schedule deletion job
        ProcessGdprDeletion::dispatch($deletionRequest)
            ->delay($deletionRequest->scheduled_deletion_at);

        return response()->json([
            'message' => 'Account deletion request submitted. Your account will be deleted in 30 days unless you cancel the request.',
            'request_id' => $deletionRequest->request_id,
            'scheduled_deletion' => $deletionRequest->scheduled_deletion_at->toISOString()
        ]);
    }

    /**
     * Cancel account deletion request.
     *
     * @OA\Delete(
     *     path="/api/v1/gdpr/delete/{requestId}",
     *     tags={"GDPR"},
     *     summary="Cancel deletion request",
     *     description="Cancel a pending account deletion request",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="requestId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion request cancelled"
     *     )
     * )
     */
    public function cancelDeletion(Request $request, string $requestId): JsonResponse
    {
        $user = $request->user();

        $deletionRequest = GdprDeleteRequest::where('user_id', $user->id)
            ->where('request_id', $requestId)
            ->where('status', 'pending')
            ->firstOrFail();

        $deletionRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        return response()->json([
            'message' => 'Account deletion request has been cancelled successfully.'
        ]);
    }
}
