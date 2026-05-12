<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="Сервис интеграции биллинга с Lifestream",
 *     version="1.0.0",
 *     description="Сервис-посредник между биллингом и ТВ-платформой Lifestream. Управляет синхронизацией абонентов, подписок и паролей."
 * )
 *
 * @OA\Server(url="/", description="Текущий сервер")
 *
 * @OA\Schema(
 *     schema="EnsureResponse",
 *     @OA\Property(property="result", type="string", example="operation_ensured",
 *         description="operation_ensured | operation_commited | no_action_required | no_subscription_rules"),
 *     @OA\Property(property="billingTransactionId", type="string", format="uuid", example="018e1234-abcd-7000-8000-000000000001")
 * )
 *
 * @OA\Schema(
 *     schema="CommitResponse",
 *     @OA\Property(property="result", type="string", example="operation_commited"),
 *     @OA\Property(property="billingTransactionId", type="string", format="uuid"),
 *     @OA\Property(property="billingStartTimestamp", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="error", type="string", example="Transaction not found")
 * )
 */
class OpenApiSpec
{
}
