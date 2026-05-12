<?php

namespace Tests\Unit;

use App\Services\LoginSanitizer;
use Tests\TestCase;

class LoginSanitizerTest extends TestCase
{
    private const LIFESTREAM_PATTERN = '/^[a-zA-Z0-9_@.+\-\/]+$/';

    public function test_ascii_login_passes_unchanged(): void
    {
        $this->assertSame('aknet_10001', LoginSanitizer::sanitize('aknet_10001'));
    }

    public function test_cyrillic_login_is_transliterated(): void
    {
        $result = LoginSanitizer::sanitize('Иванов');
        $this->assertMatchesRegularExpression(self::LIFESTREAM_PATTERN, $result);
    }

    public function test_mixed_cyrillic_ascii_login(): void
    {
        $result = LoginSanitizer::sanitize('user_Иванов_123');
        $this->assertMatchesRegularExpression(self::LIFESTREAM_PATTERN, $result);
        $this->assertStringContainsString('user_', $result);
        $this->assertStringContainsString('123', $result);
    }

    public function test_result_always_matches_lifestream_regex(): void
    {
        $logins = [
            'dc21dkab_iptv',
            'ГРУППА-ЧАТОВ',
            'user@domain.kg',
            'test+user',
            'aknet_10001',
            'ОчЕньСтРаНнЫйЛогИн',
        ];

        foreach ($logins as $login) {
            $result = LoginSanitizer::sanitize($login);
            $this->assertMatchesRegularExpression(
                self::LIFESTREAM_PATTERN,
                $result,
                "Login '{$login}' produced invalid result '{$result}'"
            );
        }
    }

    public function test_already_valid_login_is_not_modified(): void
    {
        $login = 'dc25dsha_iptv';
        $this->assertSame($login, LoginSanitizer::sanitize($login));
    }
}
