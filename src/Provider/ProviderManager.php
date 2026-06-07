<?php

namespace Ernestdefoe\OnAir\Provider;

use Flarum\Foundation\ValidationException;

/**
 * Registry of stream providers. Lite binds YouTube + Twitch in the service
 * provider; Pro appends its RTMP/HLS provider to the same collection.
 */
class ProviderManager
{
    /** @var StreamProvider[] */
    protected array $providers = [];

    /**
     * @param StreamProvider[] $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    public function add(StreamProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): ?StreamProvider
    {
        return $this->providers[$key] ?? null;
    }

    /** @return StreamProvider[] */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /** First provider that recognises the URL, or null. */
    public function resolve(string $url): ?StreamProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->matches($url)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Resolve + normalize a pasted URL into stored fields, throwing a
     * validation error if no provider matches.
     *
     * @return array{provider: string, external_id: ?string, embed_url: ?string, channel_url: string}
     */
    public function normalize(string $url): array
    {
        $provider = $this->resolve($url);

        if (! $provider) {
            throw new ValidationException([
                'channel_url' => resolve(\Flarum\Locale\TranslatorInterface::class)->trans('onair.lib.errors.no_provider'),
            ]);
        }

        return ['provider' => $provider->key()] + $provider->normalize($url);
    }
}
