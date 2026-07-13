<?php

namespace App\Domain\Flink\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Flink\Services\PricingService;
use App\Domain\Wallet\Actions\ReserveFlinkPaymentAction;
use Illuminate\Support\Facades\DB;

class CreateFlinkAction
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly ReserveFlinkPaymentAction $reserveFlinkPaymentAction,
    ) {}

    /**
     * @param  array{activity_type: string, location: string, latitude: float, longitude: float, start_date_time: string, end_date_time: string, requirements?: string, net_value: float}  $data
     */
    public function handle(Company $company, array $data): Flink
    {
        $pricing = $this->pricingService->calculate((float) $data['net_value']);

        return DB::transaction(function () use ($company, $data, $pricing) {
            $flink = $company->flinks()->create([
                'activity_type' => $data['activity_type'],
                'location' => $data['location'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'start_date_time' => $data['start_date_time'],
                'end_date_time' => $data['end_date_time'],
                'requirements' => $data['requirements'] ?? null,
                'status' => FlinkStatus::Open,
                'net_value' => $pricing['net_value'],
                'platform_margin' => $pricing['platform_margin'],
                'total_value' => $pricing['total_value'],
            ]);

            // Garante o pagamento no ato da publicação (decisão de produto — ver
            // docs/ARCHITECTURE.md). Se o saldo for insuficiente, a ValidationException
            // lançada aqui reverte toda a transação, incluindo a criação do Flink acima.
            $this->reserveFlinkPaymentAction->handle($company->user->wallet, $flink);

            return $flink;
        });
    }
}
