<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model;

use UpturnStudio\Ai\Api\ConfigInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Events\NullEventManager;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Agent\AgentRunner;
use UpturnStudio\Ai\Model\Config\ArrayConfig;
use UpturnStudio\Ai\Model\Provider\AnthropicProvider;
use UpturnStudio\Ai\Model\Provider\Driver\ProviderDriver;
use UpturnStudio\Ai\Model\Provider\GeminiProvider;
use UpturnStudio\Ai\Model\Provider\OllamaProvider;
use UpturnStudio\Ai\Model\Provider\OpenAiCompatibleProvider;
use UpturnStudio\Ai\Model\Provider\OpenAiProvider;

/**
 * Wires the SDK by hand, without Magento's ObjectManager. Unit tests and scripts use it; inside Magento the
 * same objects come from di.xml.
 */
class StandaloneFactory
{
    /**
     * Build a manager and a runner that share it.
     *
     * @param ConfigInterface|null $config
     * @param Client|null $http
     * @param Dispatcher|null $events
     * @return array{0: AiManager, 1: AgentRunner}
     */
    public static function create(
        ?ConfigInterface $config = null,
        ?Client $http = null,
        ?Dispatcher $events = null
    ): array {
        $http ??= new Client();
        $events ??= new Dispatcher(new NullEventManager());

        $drivers = [
            'anthropic' => AnthropicProvider::class,
            'openai' => OpenAiProvider::class,
            'gemini' => GeminiProvider::class,
            'openai-compatible' => OpenAiCompatibleProvider::class,
            'ollama' => OllamaProvider::class,
        ];

        $manager = new AiManager($config ?? new ArrayConfig(), $events, array_map(
            static fn (string $providerClass) => new ProviderDriver($http, $events, $providerClass),
            $drivers
        ));

        return [$manager, new AgentRunner($manager, $events)];
    }
}
