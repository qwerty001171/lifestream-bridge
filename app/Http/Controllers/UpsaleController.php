<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceSubscriptionCommitRequest;
use App\Http\Requests\ReplaceSubscriptionEnsureRequest;
use App\Http\Requests\UpsaleCommitRequest;
use App\Http\Requests\UpsaleEnsureRequest;
use App\Models\Transaction;
use App\Services\UpsaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpsaleController extends Controller
{
    public function __construct(
        private readonly UpsaleService $upsaleService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/upsale/v3/add-subscription/ensure",
     *     summary="Проверка возможности подключения подписки",
     *     tags={"Upsale"},
     *     @OA\Parameter(name="accountId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="accountLogin", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="newOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="qsTransactionId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="trialDays", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Результат проверки", @OA\JsonContent(ref="#/components/schemas/EnsureResponse")),
     *     @OA\Response(response=422, description="Ошибка валидации")
     * )
     */
    public function addSubscriptionEnsure(UpsaleEnsureRequest $request): JsonResponse
    {
        try {
            $result = $this->upsaleService->ensure(
                operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
                accountId:       $request->query('accountId'),
                accountLogin:    $request->query('accountLogin'),
                newOfferId:      $request->query('newOfferId'),
                oldOfferId:      null,
                qsTransactionId: $request->query('qsTransactionId'),
                trialDays:       (int) $request->query('trialDays', 0),
                context:         $this->extractContext($request->validated()),
                ip:              $request->ip(),
                userAgent:       $request->userAgent()
            );

            return response()->json($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/upsale/v3/add-subscription/commit",
     *     summary="Подтверждение подключения подписки",
     *     tags={"Upsale"},
     *     @OA\Parameter(name="accountId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="accountLogin", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="newOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="qsTransactionId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="billingTransactionId", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="serviceStartTimestamp", in="query", required=false, @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response=200, description="Подписка подтверждена", @OA\JsonContent(ref="#/components/schemas/CommitResponse")),
     *     @OA\Response(response=404, description="Транзакция не найдена", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=409, description="Транзакция в ошибочном состоянии", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=422, description="Ошибка валидации")
     * )
     */
    public function addSubscriptionCommit(UpsaleCommitRequest $request): JsonResponse
    {
        try {
            $result = $this->upsaleService->commit(
                billingTransactionId:  $request->query('billingTransactionId'),
                qsTransactionId:       $request->query('qsTransactionId'),
                serviceStartTimestamp: $request->query('serviceStartTimestamp'),
                context:               $this->extractContext($request->validated()),
                ip:                    $request->ip(),
                userAgent:             $request->userAgent()
            );

            return response()->json($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Transaction not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/upsale/v3/replace-subscription/ensure",
     *     summary="Проверка возможности смены подписки",
     *     tags={"Upsale"},
     *     @OA\Parameter(name="accountId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="accountLogin", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="newOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="oldOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="qsTransactionId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Результат проверки", @OA\JsonContent(ref="#/components/schemas/EnsureResponse")),
     *     @OA\Response(response=422, description="Ошибка валидации")
     * )
     */
    public function replaceSubscriptionEnsure(ReplaceSubscriptionEnsureRequest $request): JsonResponse
    {
        try {
            $result = $this->upsaleService->ensure(
                operationType:   Transaction::OPERATION_REPLACE_SUBSCRIPTION,
                accountId:       $request->query('accountId'),
                accountLogin:    $request->query('accountLogin'),
                newOfferId:      $request->query('newOfferId'),
                oldOfferId:      $request->query('oldOfferId'),
                qsTransactionId: $request->query('qsTransactionId'),
                trialDays:       (int) $request->query('trialDays', 0),
                context:         $this->extractContext($request->validated()),
                ip:              $request->ip(),
                userAgent:       $request->userAgent()
            );

            return response()->json($result);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/upsale/v3/replace-subscription/commit",
     *     summary="Подтверждение смены подписки",
     *     tags={"Upsale"},
     *     @OA\Parameter(name="accountId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="accountLogin", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="newOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="oldOfferId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="qsTransactionId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="billingTransactionId", in="query", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="serviceStartTimestamp", in="query", required=false, @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response=200, description="Смена подтверждена", @OA\JsonContent(ref="#/components/schemas/CommitResponse")),
     *     @OA\Response(response=404, description="Транзакция не найдена", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=409, description="Транзакция в ошибочном состоянии", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=422, description="Ошибка валидации")
     * )
     */
    public function replaceSubscriptionCommit(ReplaceSubscriptionCommitRequest $request): JsonResponse
    {
        try {
            $result = $this->upsaleService->commit(
                billingTransactionId:  $request->query('billingTransactionId'),
                qsTransactionId:       $request->query('qsTransactionId'),
                serviceStartTimestamp: $request->query('serviceStartTimestamp'),
                context:               $this->extractContext($request->validated()),
                ip:                    $request->ip(),
                userAgent:             $request->userAgent()
            );

            return response()->json($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Transaction not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function extractContext(array $validated): array
    {
        $contextKeys = ['newOfferName', 'oldOfferName', 'clientIp', 'userInfo', 'deviceInfo', 'appInfo'];
        $context = array_filter(
            array_intersect_key($validated, array_flip($contextKeys)),
            fn($v) => $v !== null
        );

        return $context;
    }

    private function errorResponse(Throwable $e): JsonResponse
    {
        Log::error('UpsaleController: unhandled exception', [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        return response()->json(['error' => 'Internal server error'], 500);
    }
}
