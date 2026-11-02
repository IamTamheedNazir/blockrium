<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class IntegrationSettingsService
{
    public function boolean(string $key, bool $fallback = false): bool
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function get(string $key, $fallback = null)
    {
        if (!DB::getSchemaBuilder()->hasTable('integration_settings')) {
            return $fallback;
        }

        $setting = DB::table('integration_settings')->where('key', $key)->first();
        if (!$setting) {
            return $fallback;
        }

        if ((bool) $setting->is_encrypted) {
            try {
                return Crypt::decryptString((string) $setting->value);
            } catch (\Throwable $exception) {
                report($exception);
                return $fallback;
            }
        }

        return $setting->value;
    }

    public function has(string $key): bool
    {
        if (!DB::getSchemaBuilder()->hasTable('integration_settings')) {
            return false;
        }

        return DB::table('integration_settings')
            ->where('key', $key)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    public function put(string $key, ?string $value, bool $encrypted, ?int $adminId = null): void
    {
        if (!DB::getSchemaBuilder()->hasTable('integration_settings')) {
            throw new \RuntimeException('Integration settings table is not installed.');
        }

        $storedValue = $value;
        if ($encrypted && $value !== null && $value !== '') {
            $storedValue = Crypt::encryptString($value);
        }

        DB::table('integration_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $encrypted,
                'updated_by_admin_id' => $adminId,
                'updated_at' => now(),
            ]
        );
    }

    public function forget(string $key): void
    {
        if (DB::getSchemaBuilder()->hasTable('integration_settings')) {
            DB::table('integration_settings')->where('key', $key)->delete();
        }
    }

    public function masked(string $key, $fallback = null): ?string
    {
        $value = $this->get($key, $fallback);
        if (!$value) {
            return null;
        }

        $value = (string) $value;
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
    }
}
