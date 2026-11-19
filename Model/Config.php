<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use UpturnStudio\Ai\Api\ConfigInterface;

/**
 * Provider configuration from Stores > Configuration > UpturnStudio > AI, di.xml and app/etc/env.php.
 *
 * Layers, later ones winning: the built-in provider groups in system config; extra named providers passed
 * through the "providers" di.xml argument; and "ai/providers/<name>" in env.php.
 */
class Config implements ConfigInterface
{
    private const XML_PATH = 'upturnstudio_ai/';

    /**
     * Built-in provider name => system config group.
     */
    private const GROUPS = [
        'anthropic' => 'anthropic',
        'openai' => 'openai',
        'gemini' => 'gemini',
        'ollama' => 'ollama',
        'openai-compatible' => 'openai_compatible',
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param DeploymentConfig $deploymentConfig
     * @param array<string,array<string,mixed>> $providers Extra named providers. Each needs "driver" and may give
     *                                                       "url", "models", "headers" and "key_config_path" (a
     *                                                       config path holding the encrypted API key).
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly array $providers = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function providerConfig(string $name): ?array
    {
        $config = isset(self::GROUPS[$name]) ? $this->builtIn($name, self::GROUPS[$name]) : [];

        if (isset($this->providers[$name])) {
            $extra = $this->providers[$name];
            if (isset($extra['key_config_path'])) {
                $extra['key'] = $this->decrypt((string) $this->scopeConfig->getValue($extra['key_config_path']));
                unset($extra['key_config_path']);
            }
            $config = array_replace_recursive($config, $extra);
        }

        $override = $this->deploymentConfig->get('ai/providers/' . $name);
        if (is_array($override)) {
            $config = array_replace_recursive($config, $override);
        }

        return $config === [] ? null : $config + ['driver' => $name];
    }

    /**
     * @inheritDoc
     */
    public function defaultProvider(string $capability = 'text'): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH . 'general/default_' . $capability . '_provider',
            ScopeInterface::SCOPE_STORE
        );

        if ($value === '' && $capability !== 'text') {
            return $this->defaultProvider();
        }

        return $value === '' ? 'anthropic' : $value;
    }

    /**
     * The configuration of a built-in provider from its system config group.
     *
     * @param string $name
     * @param string $group
     * @return array<string, mixed>
     */
    private function builtIn(string $name, string $group): array
    {
        $value = fn (string $field): string => trim((string) $this->scopeConfig->getValue(
            self::XML_PATH . $group . '/' . $field,
            ScopeInterface::SCOPE_STORE
        ));

        $models = array_filter([
            'default' => $value('text_model'),
            'cheapest' => $value('cheapest_text_model'),
            'smartest' => $value('smartest_text_model'),
        ]);

        return array_filter([
            'driver' => $name,
            'key' => $this->decrypt($value('api_key')),
            'url' => $value('url'),
            'models' => $models === [] ? null : ['text' => $models],
        ]);
    }

    /**
     * The plain API key for a stored value.
     *
     * Fields declared in config.xml with the Encrypted backend model are already decrypted by the time
     * ScopeConfig returns them, so only values still in Magento's encrypted form ("0:3:…") are decrypted here.
     * Decrypting a plain key would turn it into garbage.
     *
     * @param string $value
     */
    private function decrypt(string $value): string
    {
        if ($value === '' || !preg_match('/^\d+:\d+:/', $value)) {
            return $value;
        }

        return (string) $this->encryptor->decrypt($value);
    }
}
