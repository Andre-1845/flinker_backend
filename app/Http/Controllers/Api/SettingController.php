<?php

namespace App\Http\Controllers\Api;

use App\Domain\Settings\Services\SettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Expõe configurações administráveis que o frontend precisa conhecer (achado
 * #15 da auditoria 2026-10-01: a margem da plataforma era hardcoded em dois
 * lugares do frontend — CreateFlink.tsx com 7% e CompanyWallet.tsx com 8% — e
 * nenhum dos dois refletia o valor real usado pelo PricingService).
 */
class SettingController extends Controller
{
    public function platformMargin(SettingsService $settings): JsonResponse
    {
        return response()->json([
            'data' => [
                'platform_margin_percent' => $settings->getFloat(
                    'platform_margin_percent',
                    (float) config('flinker.platform_margin_percent'),
                ),
            ],
        ]);
    }
}
