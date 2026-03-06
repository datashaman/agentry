<?php

namespace App\Support;

class AiProviders
{
    /**
     * Get provider IDs that have API keys configured.
     *
     * @return array<string, string> Map of provider ID => label
     */
    public static function available(): array
    {
        $providers = config('ai.providers', []);
        $available = [];

        foreach ($providers as $id => $config) {
            $key = $config['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $available[$id] = $config['label'] ?? ucfirst($id);
            }
        }

        return $available;
    }

    /**
     * Check if a provider ID is valid (known).
     */
    public static function isValid(string $id): bool
    {
        return isset(config('ai.providers', [])[$id]);
    }
}
