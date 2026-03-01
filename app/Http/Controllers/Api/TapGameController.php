<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\TapEvent;
use App\Support\TelegramInitDataVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TapGameController extends Controller
{
    public function __construct(
        private readonly TelegramInitDataVerifier $telegramVerifier
    ) {
    }

    public function syncPlayer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $this->telegramVerifier->verify($data['init_data']);

        $player = Player::query()->firstOrCreate(
            ['telegram_user_id' => $telegramUser['telegram_user_id']],
            [
                'username' => $telegramUser['username'],
                'first_name' => $telegramUser['first_name'],
                'last_name' => $telegramUser['last_name'],
            ],
        );

        $player->fill([
            'username' => $telegramUser['username'] ?? $player->username,
            'first_name' => $telegramUser['first_name'] ?? $player->first_name,
            'last_name' => $telegramUser['last_name'] ?? $player->last_name,
        ])->save();

        return response()->json([
            'player' => $this->playerPayload($player->fresh()),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $data = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $this->telegramVerifier->verify($data['init_data']);
        $player = Player::query()
            ->where('telegram_user_id', $telegramUser['telegram_user_id'])
            ->firstOrFail();

        return response()->json([
            'player' => $this->playerPayload($player),
        ]);
    }

    public function tap(Request $request): JsonResponse
    {
        $maxTaps = max(1, config('taptoearn.max_taps_per_request', 50));
        $coinsPerTap = max(1, config('taptoearn.coins_per_tap', 1));
        $maxTapsPerMinute = max($maxTaps, config('taptoearn.max_taps_per_minute', 600));
        $maxTapsPer5Seconds = max($maxTaps, config('taptoearn.max_taps_per_5_seconds', 80));

        $data = $request->validate([
            'init_data' => ['required', 'string'],
            'tap_count' => ['sometimes', 'integer', 'min:1', 'max:'.$maxTaps],
            'source' => ['sometimes', 'string', 'max:32'],
            'client_nonce' => ['required', 'string', 'max:64'],
            'client_seq' => ['required', 'integer', 'min:1'],
            'meta' => ['sometimes', 'array'],
        ]);

        $telegramUser = $this->telegramVerifier->verify($data['init_data']);
        $tapCount = (int) ($data['tap_count'] ?? 1);
        $clientSeq = (int) $data['client_seq'];

        $result = DB::transaction(function () use (
            $clientSeq,
            $coinsPerTap,
            $data,
            $maxTapsPer5Seconds,
            $maxTapsPerMinute,
            $tapCount,
            $telegramUser
        ): array {
            $player = Player::query()
                ->where('telegram_user_id', $telegramUser['telegram_user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $duplicateByNonce = TapEvent::query()
                ->where('player_id', $player->id)
                ->where('client_nonce', $data['client_nonce'])
                ->first();

            if ($duplicateByNonce) {
                return [
                    'player' => $player->fresh(),
                    'coins_earned' => 0,
                ];
            }

            $eventBySeq = TapEvent::query()
                ->where('player_id', $player->id)
                ->where('client_seq', $clientSeq)
                ->first();

            if ($eventBySeq) {
                return [
                    'player' => $player->fresh(),
                    'coins_earned' => 0,
                ];
            }

            $this->assertTapRateLimit($player, $tapCount, $maxTapsPerMinute);
            $this->assertShortBurstLimit($player, $tapCount, $maxTapsPer5Seconds);

            $coinsEarned = $tapCount * $coinsPerTap;
            $player->total_taps += $tapCount;
            $player->coin_balance += $coinsEarned;
            $player->last_tap_at = now();
            $player->last_client_seq = max((int) $player->last_client_seq, $clientSeq);
            $player->save();

            TapEvent::query()->create([
                'player_id' => $player->id,
                'tap_count' => $tapCount,
                'coins_earned' => $coinsEarned,
                'source' => $data['source'] ?? 'app',
                'client_nonce' => $data['client_nonce'],
                'client_seq' => $clientSeq,
                'meta' => $data['meta'] ?? null,
            ]);

            return [
                'player' => $player->fresh(),
                'coins_earned' => $coinsEarned,
            ];
        });

        return response()->json([
            'coins_earned' => $result['coins_earned'],
            'player' => $this->playerPayload($result['player']),
        ]);
    }

    private function assertTapRateLimit(Player $player, int $tapCount, int $maxTapsPerMinute): void
    {
        $now = now();
        $windowStart = $player->tap_window_started_at;

        if ($windowStart === null || $windowStart->lte($now->copy()->subMinute())) {
            $player->tap_window_started_at = $now;
            $player->tap_window_count = 0;
        }

        $newWindowCount = $player->tap_window_count + $tapCount;
        if ($newWindowCount > $maxTapsPerMinute) {
            throw ValidationException::withMessages([
                'tap_count' => ['Tap rate too high. Please slow down.'],
            ]);
        }

        $player->tap_window_count = $newWindowCount;
    }

    private function assertShortBurstLimit(Player $player, int $tapCount, int $maxTapsPer5Seconds): void
    {
        $burstCount = (int) TapEvent::query()
            ->where('player_id', $player->id)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->sum('tap_count');

        if (($burstCount + $tapCount) > $maxTapsPer5Seconds) {
            throw ValidationException::withMessages([
                'tap_count' => ['Tap burst too high.'],
            ]);
        }
    }

    private function playerPayload(Player $player): array
    {
        return [
            'id' => $player->id,
            'telegram_user_id' => $player->telegram_user_id,
            'username' => $player->username,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'total_taps' => $player->total_taps,
            'coin_balance' => $player->coin_balance,
            'last_tap_at' => $player->last_tap_at,
        ];
    }
}
