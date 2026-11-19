<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function UpturnStudio\Ai\agent;

/**
 * Sends one prompt to a configured provider. A live smoke test: it calls the real API and costs tokens.
 */
class Prompt extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('upturn:ai:prompt')
            ->setDescription('Send a prompt to an AI provider and print the response (calls the real API).')
            ->addArgument('prompt', InputArgument::REQUIRED, 'The prompt to send')
            ->addOption(
                'provider',
                'p',
                InputOption::VALUE_REQUIRED,
                'Provider name, or a comma-separated list to fail over in order'
            )
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Model to use instead of the provider default')
            ->addOption('instructions', 'i', InputOption::VALUE_REQUIRED, 'System instructions', '');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = $input->getOption('provider');
        if (is_string($provider) && str_contains($provider, ',')) {
            $provider = array_values(array_filter(array_map('trim', explode(',', $provider))));
        }

        try {
            $response = agent(instructions: (string) $input->getOption('instructions'))->prompt(
                (string) $input->getArgument('prompt'),
                provider: $provider ?: null,
                model: $input->getOption('model') ?: null
            );
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>%s: %s</error>', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln($response->text);
        $output->writeln(sprintf(
            '<comment>%s / %s — %d input tokens, %d output tokens, %d step(s)</comment>',
            $response->meta->provider,
            $response->meta->model,
            $response->usage->inputTokens,
            $response->usage->outputTokens,
            count($response->steps)
        ));

        return Command::SUCCESS;
    }
}
