<?php

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client as AnthropicClient;

/**
 * Thin wrapper around the official Anthropic PHP SDK so the rest of the app
 * can keep using a single `messages([...])` call without dealing with the
 * SDK's named-parameter API (or future SDK upgrades).
 *
 * Existing call sites pass an OpenAI-style array — this method converts it
 * to the SDK's camelCase named args and returns a stdClass with a unified
 * shape:
 *   { content: [ { text: "..." } ] }
 *
 * Usage:
 *   $resp = app(AiClient::class)->messages([
 *       'model'       => 'claude-sonnet-4-5',
 *       'max_tokens'  => 200,
 *       'temperature' => 0,
 *       'system'      => 'You are precise.',
 *       'messages'    => [['role' => 'user', 'content' => 'hi']],
 *   ]);
 *   $text = $resp->content[0]->text ?? '';
 */
class AiClient
{
    private ?AnthropicClient $client = null;

    public function isConfigured(): bool
    {
        return !empty($this->resolveKey());
    }

    public function messages(array $opts): \stdClass
    {
        if (!class_exists(AnthropicClient::class)) {
            throw new \RuntimeException('Anthropic SDK not installed (composer require anthropic-ai/sdk).');
        }
        $key = $this->resolveKey();
        if (!$key) throw new \RuntimeException('Anthropic API key not configured.');

        if (!$this->client) $this->client = new AnthropicClient(apiKey: $key);

        // Translate snake_case → camelCase for the SDK.
        $sdkArgs = array_filter([
            'maxTokens'   => $opts['max_tokens']  ?? 1024,
            'messages'    => $this->normalizeMessages($opts['messages'] ?? []),
            'model'       => $opts['model']       ?? 'claude-haiku-4-5',
            'system'      => $opts['system']      ?? null,
            'temperature' => $opts['temperature'] ?? null,
        ], fn ($v) => $v !== null);

        // SDK uses property access, not method call.
        $resp = $this->client->messages->create(...$sdkArgs);

        // Normalize the response to a stdClass shape callers already use.
        $blocks = [];
        foreach (($resp->content ?? []) as $block) {
            $obj = new \stdClass();
            $obj->text = (string) ($block->text ?? '');
            $blocks[] = $obj;
        }
        $out = new \stdClass();
        $out->content = $blocks;
        return $out;
    }

    /**
     * Tiny "ping" call used by the Settings → AI Test connection button.
     */
    public function ping(?string $modelOverride = null): array
    {
        try {
            $resp = $this->messages([
                'model'      => $modelOverride ?: 'claude-haiku-4-5',
                'max_tokens' => 10,
                'temperature'=> 0,
                'messages'   => [['role' => 'user', 'content' => 'Reply with the single word: OK']],
            ]);
            return ['ok' => true, 'text' => trim((string) ($resp->content[0]->text ?? ''))];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function resolveKey(): ?string
    {
        try {
            $s = (string) \App\Models\Setting::getValue('anthropic_api_key');
            if ($s !== '') return $s;
        } catch (\Throwable) {}
        return config('services.anthropic.api_key') ?: null;
    }

    /**
     * SDK accepts either a string or an array of content blocks per message.
     * Make sure each `content` is an array of {type, text|...} blocks.
     */
    private function normalizeMessages(array $messages): array
    {
        return array_map(function ($m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            if (is_string($content)) {
                $content = [['type' => 'text', 'text' => $content]];
            }
            return ['role' => $role, 'content' => $content];
        }, $messages);
    }
}
