<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Enums;

/**
 * The built-in provider drivers.
 */
enum Lab: string
{
    case Anthropic = 'anthropic';
    case OpenAI = 'openai';
    case Gemini = 'gemini';
    case OpenAICompatible = 'openai-compatible';
    case Ollama = 'ollama';
}
