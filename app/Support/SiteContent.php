<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SiteContent
{
    private const SOCIAL_PLATFORMS = [
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'other' => null,
    ];

    public function homepageForAdmin(): array
    {
        $homepage = $this->decodeSetting('platform.homepage_content');

        return array_replace_recursive([
            'hero' => [
                'eyebrow' => $this->localized('site.hero.eyebrow'),
                'title_before' => $this->localized('site.hero.title_before'),
                'title_accent' => $this->localized('site.hero.title_accent'),
                'title_after' => $this->localized('site.hero.title_after'),
                'description' => $this->localized('site.hero.description'),
                'trust' => $this->localized('site.hero.trust'),
                'caption' => $this->localized('site.hero.caption'),
            ],
            'about' => [
                'label' => $this->localized('site.landing.about_label'),
                'title' => $this->localized('site.landing.about_title'),
                'description' => $this->localized('site.landing.about_description'),
            ],
            'how_it_works' => [
                'label' => $this->localized('site.landing.steps_label'),
                'title' => $this->localized('site.landing.steps_title'),
                'description' => $this->localized('site.landing.steps_description'),
                'step_1_title' => $this->localized('site.landing.step_one_title'),
                'step_1_description' => $this->localized('site.landing.step_one_description'),
                'step_2_title' => $this->localized('site.landing.step_two_title'),
                'step_2_description' => $this->localized('site.landing.step_two_description'),
                'step_3_title' => $this->localized('site.landing.step_three_title'),
                'step_3_description' => $this->localized('site.landing.step_three_description'),
            ],
            'slides' => [],
        ], $homepage);
    }

    /**
     * @return array{en: string, bn: string}
     */
    public function termsForAdmin(): array
    {
        $terms = $this->decodeSetting('platform.terms_and_conditions');

        return [
            'en' => is_string($terms['en'] ?? null) ? $terms['en'] : __('site.terms.default', [], 'en'),
            'bn' => is_string($terms['bn'] ?? null) ? $terms['bn'] : __('site.terms.default', [], 'bn'),
        ];
    }

    /**
     * @return array{content: string, version: int}
     */
    public function termsForLocale(string $locale): array
    {
        $locale = in_array($locale, ['en', 'bn'], true) ? $locale : 'en';

        return [
            'content' => $this->termsForAdmin()[$locale],
            'version' => $this->termsVersion(),
        ];
    }

    public function termsVersion(): int
    {
        return (int) (DB::table('platform_settings')
            ->where('key', 'platform.terms_and_conditions')
            ->orderByDesc('version')
            ->value('version') ?? 0);
    }

    public function homepage(string $locale): array
    {
        $locale = in_array($locale, ['en', 'bn'], true) ? $locale : 'en';
        $homepage = $this->homepageForAdmin();

        foreach (['hero', 'about', 'how_it_works'] as $section) {
            foreach ($homepage[$section] as $field => $translations) {
                if (is_array($translations)) {
                    $value = array_key_exists($locale, $translations)
                        ? $translations[$locale]
                        : ($translations['en'] ?? '');

                    $homepage[$section][$field] = is_string($value) ? $value : '';
                }
            }
        }

        $homepage['slides'] = array_map(
            fn (string $path): array => [
                'url' => Storage::disk('public')->url($path),
            ],
            array_filter($homepage['slides'], 'is_string'),
        );

        return $homepage;
    }

    public function socialLinksForAdmin(): array
    {
        $links = $this->decodeSetting('platform.social_links');
        $result = [];

        foreach (self::SOCIAL_PLATFORMS as $platform => $defaultLabel) {
            $stored = $links[$platform] ?? [];
            $iconPath = $stored['icon_path'] ?? null;

            $result[$platform] = [
                'label' => $platform === 'other'
                    ? (string) ($stored['label'] ?? '')
                    : $defaultLabel,
                'enabled' => (bool) ($stored['enabled'] ?? false),
                'url' => (string) ($stored['url'] ?? ''),
                'icon_path' => is_string($iconPath) ? $iconPath : null,
                'icon_url' => is_string($iconPath) && $iconPath !== ''
                    ? Storage::disk('public')->url($iconPath)
                    : null,
            ];
        }

        return $result;
    }

    public function activeSocialLinks(): array
    {
        $activeLinks = [];

        foreach ($this->socialLinksForAdmin() as $platform => $link) {
            $url = $link['url'];
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if (
                ! $link['enabled']
                || ! is_string($scheme)
                || ! in_array(strtolower($scheme), ['http', 'https'], true)
                || filter_var($url, FILTER_VALIDATE_URL) === false
            ) {
                continue;
            }

            $activeLinks[] = [
                'platform' => $platform,
                'label' => $link['label'],
                'url' => $url,
                'icon_url' => $link['icon_url'],
            ];
        }

        return $activeLinks;
    }

    private function localized(string $translationKey): array
    {
        return [
            'en' => __($translationKey, [], 'en'),
            'bn' => __($translationKey, [], 'bn'),
        ];
    }

    private function decodeSetting(string $key): array
    {
        $value = DB::table('platform_settings')
            ->where('key', $key)
            ->orderByDesc('version')
            ->value('value');

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \UnexpectedValueException("The {$key} setting must contain a JSON object.");
        }

        return $decoded;
    }
}
