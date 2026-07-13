<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;

        abort_unless($wallet, 404, 'Carteira não encontrada.');

        $transactions = Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(TransactionResource::collection($transactions)->response()->getData(true));
    }
}
