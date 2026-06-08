<?php

namespace Ernestdefoe\OnAir\Provider;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;

/**
 * Registry of stream providers. Lite binds YouTube + Twitch in the service
 * provider; Pro appends its RTMP/HLS provider to the same collection.
 */
class ProviderManager
{
    /** @var StreamProvider[] */
    protected array $providers = [];

    protected ?TranslatorInterface $translator;

    /**
     * @param StreamProvider[] $providers
     */
    public function __construct(array $providers = [], ?TranslatorInterface $translator = null)
    {
        $this->translator = $translator;

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
            $key = 'onair.lib.errors.no_provider';
            throw new ValidationException([
                'channel_url' => $this->translator ? $this->translator->trans($key) : $key,
            ]);
        }

        return ['provider' => $provider->key()] + $provider->normalize($url);
    }
}
