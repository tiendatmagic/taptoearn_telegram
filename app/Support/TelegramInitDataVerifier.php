<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class TelegramInitDataVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $initData): array
    {
        if (config('taptoearn.allow_unverified_demo') && str_starts_with($initData, 'debug:')) {
            return $this->parseDebugInitData($initData);
        }

        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') {
            throw ValidationException::withMessages([
                'init_data' => ['TELEGRAM_BOT_TOKEN is missing.'],
            ]);
        }

        parse_str($initData, $data);
        if (!is_array($data) || empty($data['hash'])) {
            throw ValidationException::withMessages([
                'init_data' => ['Invalid Telegram init data.'],
            ]);
        }

        $hash = (string) $data['hash'];
        unset($data['hash']);

        $authDate = isset($data['auth_date']) ? (int) $data['auth_date'] : 0;
        $ttl = max(60, (int) config('taptoearn.init_data_ttl_seconds', 86400));
        if ($authDate <= 0 || (time() - $authDate) > $ttl) {
            throw ValidationException::withMessages([
                'init_data' => ['Telegram session expired.'],
            ]);
        }

        $dataCheckString = $this->buildDataCheckString($data);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($computedHash, $hash)) {
            throw ValidationException::withMessages([
                'init_data' => ['Telegram signature mismatch.'],
            ]);
        }

        $userRaw = $data['user'] ?? null;
        if (!is_string($userRaw)) {
            throw ValidationException::withMessages([
                'init_data' => ['Telegram user payload missing.'],
            ]);
        }

        $user = json_decode($userRaw, true);
        if (!is_array($user) || !isset($user['id'])) {
            throw ValidationException::withMessages([
                'init_data' => ['Telegram user payload invalid.'],
            ]);
        }

        return [
            'telegram_user_id' => (string) $user['id'],
            'username' => isset($user['username']) ? (string) $user['username'] : null,
            'first_name' => isset($user['first_name']) ? (string) $user['first_name'] : null,
            'last_name' => isset($user['last_name']) ? (string) $user['last_name'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildDataCheckString(array $data): string
    {
        ksort($data);
        $parts = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $parts[] = $key.'='.$value;
        }

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDebugInitData(string $initData): array
    {
        $raw = substr($initData, 6);
        $telegramUserId = trim($raw) !== '' ? trim($raw) : 'demo_user';

        return [
            'telegram_user_id' => $telegramUserId,
            'username' => null,
            'first_name' => 'Demo',
            'last_name' => null,
        ];
    }
}
