<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use UpturnStudio\Ai\Model\Config;

/**
 * @covers \UpturnStudio\Ai\Model\Config
 */
class ConfigTest extends TestCase
{
    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $providers
     * @param array<string, mixed>|null $envOverride
     */
    private function config(array $values, array $providers = [], ?array $envOverride = null): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn (string $path) => $values[$path] ?? null);

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(fn (string $value) => 'decrypted(' . $value . ')');

        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn($envOverride);

        return new Config($scopeConfig, $encryptor, $deploymentConfig, $providers);
    }

    public function testAKeyMagentoAlreadyDecryptedIsUsedAsIs(): void
    {
        $config = $this->config([
            'upturnstudio_ai/anthropic/api_key' => 'sk-ant-api03-plain',
            'upturnstudio_ai/anthropic/text_model' => 'claude-opus-5-5',
        ]);

        $this->assertSame([
            'driver' => 'anthropic',
            'key' => 'sk-ant-api03-plain',
            'models' => ['text' => ['default' => 'claude-opus-5-5']],
        ], $config->providerConfig('anthropic'));
    }

    public function testAKeyStillInEncryptedFormIsDecrypted(): void
    {
        $config = $this->config(['upturnstudio_ai/anthropic/api_key' => '0:3:abcdef']);

        $this->assertSame('decrypted(0:3:abcdef)', $config->providerConfig('anthropic')['key']);
    }

    public function testExtraProvidersReadTheirKeyFromAConfigPathAndEnvOverrides(): void
    {
        $config = $this->config(
            ['my_module/ai/key' => '0:3:xyz'],
            ['claude-eu' => ['driver' => 'anthropic', 'url' => 'https://eu.example.com', 'key_config_path' => 'my_module/ai/key']],
            ['url' => 'https://override.example.com']
        );

        $this->assertSame([
            'driver' => 'anthropic',
            'url' => 'https://override.example.com',
            'key' => 'decrypted(0:3:xyz)',
        ], $config->providerConfig('claude-eu'));
    }

    public function testUnconfiguredProvidersAreNullAndTheDefaultFallsBackToAnthropic(): void
    {
        $config = $this->config([]);

        $this->assertNull($config->providerConfig('nope'));
        $this->assertSame('anthropic', $config->defaultProvider());
        $this->assertSame('anthropic', $config->defaultProvider('image'));
    }
}
