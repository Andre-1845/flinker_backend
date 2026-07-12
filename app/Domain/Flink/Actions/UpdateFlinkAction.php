<?php

namespace App\Domain\Flink\Actions;

use App\Domain\Flink\Models\Flink;
use App\Domain\Flink\Services\PricingService;

class UpdateFlinkAction
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Flink $flink, array $data): Flink
    {
        if (array_key_exists('net_value', $data)) {
            $pricing = $this->pricingService->calculate((float) $data['net_value']);
            $data['net_value'] = $pricing['net_value'];
            $data['platform_margin'] = $pricing['platform_margin'];
            $data['total_value'] = $pricing['total_value'];
        }

        $flink->update($data);

        return $flink->fresh();
    }
}
