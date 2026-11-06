<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SettingsController extends Controller
{
    public function edit(SiteContent $siteContent): View
    {
        $usdtQrPath = $this->currentSetting('platform.payment_qr.usdt', '');
        $homepage = $siteContent->homepageForAdmin();

        return view('admin.settings.edit', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'annualFee' => $this->currentSetting('platform.annual_fee', '0'),
            'minimumWithdrawal' => $this->currentSetting('platform.minimum_withdrawal', '0'),
            'referralRate' => $this->currentSetting('platform.referral_commission_percent', '0'),
            'referralCommissionType' => $this->currentSetting('platform.referral_commission_type', 'percentage'),
            'displayCurrency' => $this->currentSetting('platform.display_currency', 'BDT'),
            'usdtToBdtRate' => $this->currentExchangeRate(),
            'bdtPaymentInstructions' => $this->currentSetting('platform.payment_instructions.bdt', ''),
            'usdtPaymentInstructions' => $this->currentSetting('platform.payment_instructions.usdt', ''),
            'usdtQrUrl' => $usdtQrPath !== '' ? Storage::disk('public')->url($usdtQrPath) : null,
            'terms' => $siteContent->termsForAdmin(),
            'homepage' => $homepage,
            'heroSlideUrls' => array_map(
                fn (string $path): array => ['path' => $path, 'url' => Storage::disk('public')->url($path)],
                array_filter($homepage['slides'], 'is_string'),
            ),
            'socialLinks' => $siteContent->socialLinksForAdmin(),
        ]);
    }

    public function update(Request $request, SiteContent $siteContent): RedirectResponse
    {
        $request->merge([
            'referral_commission_type' => $request->input('referral_commission_type', 'percentage'),
        ]);
        $socialLinksBefore = $siteContent->socialLinksForAdmin();
        $termsBefore = $siteContent->termsForAdmin();
        $rules = [
            'annual_fee' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'minimum_withdrawal' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'referral_commission_type' => ['required', 'in:percentage,fixed'],
            'referral_commission_percent' => [
                'exclude_unless:referral_commission_type,percentage',
                'required',
                'nullable',
                'numeric',
                'between:0,100',
                'regex:/^\d{1,3}(?:\.\d{1,4})?$/',
            ],
            'referral_commission_fixed_bdt' => [
                'exclude_unless:referral_commission_type,fixed',
                'required',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999999999.99',
                'regex:/^\d{1,16}(?:\.\d{1,2})?$/',
            ],
            'display_currency' => ['required', 'in:BDT,USDT'],
            'usdt_to_bdt_rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.99999999', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/'],
            'bdt_payment_instructions' => ['nullable', 'string', 'max:5000'],
            'usdt_payment_instructions' => ['nullable', 'string', 'max:5000'],
            'usdt_qr_code' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'terms' => ['sometimes', 'array:en,bn'],
            'terms.en' => ['required_with:terms', 'string', 'max:20000'],
            'terms.bn' => ['required_with:terms', 'string', 'max:20000'],
            'homepage' => ['sometimes', 'array:hero,about,how_it_works'],
            'hero_slides' => ['nullable', 'array', 'max:10'],
            'hero_slides.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'remove_hero_slides' => ['sometimes', 'array'],
            'remove_hero_slides.*' => ['string', 'max:255'],
            'social_links' => ['sometimes', 'array:telegram,whatsapp,facebook,instagram,other'],
        ];

        foreach ([
            'hero' => ['eyebrow', 'title_before', 'title_accent', 'title_after', 'description', 'trust', 'caption'],
            'about' => ['label', 'title', 'description'],
            'how_it_works' => ['label', 'title', 'description', 'step_1_title', 'step_1_description', 'step_2_title', 'step_2_description', 'step_3_title', 'step_3_description'],
        ] as $section => $fields) {
            $rules["homepage.{$section}"] = ['sometimes', 'array:'.implode(',', $fields)];

            foreach ($fields as $field) {
                $rules["homepage.{$section}.{$field}"] = ['sometimes', 'array:en,bn'];

                foreach (['en', 'bn'] as $locale) {
                    $rules["homepage.{$section}.{$field}.{$locale}"] = [
                        'sometimes',
                        'nullable',
                        'string',
                        'max:'.($field === 'description' || str_ends_with($field, '_description') ? 5000 : 255),
                    ];
                }
            }
        }

        foreach (['telegram', 'whatsapp', 'facebook', 'instagram', 'other'] as $platform) {
            $socialFields = $platform === 'other'
                ? 'enabled,url,icon,remove_icon,label'
                : 'enabled,url,icon,remove_icon';
            $rules["social_links.{$platform}"] = ['required_with:social_links', "array:{$socialFields}"];
            $rules["social_links.{$platform}.enabled"] = ['required_with:social_links', 'boolean'];
            $rules["social_links.{$platform}.url"] = [
                'nullable',
                "required_if:social_links.{$platform}.enabled,1",
                'url:http,https',
                'max:2048',
            ];
            $iconRules = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];
            if ($socialLinksBefore[$platform]['icon_path'] === null) {
                $iconRules[] = "required_if:social_links.{$platform}.enabled,1";
            }
            $rules["social_links.{$platform}.icon"] = $iconRules;
            $rules["social_links.{$platform}.remove_icon"] = [
                'nullable',
                'boolean',
                "prohibited_if:social_links.{$platform}.enabled,1",
            ];
        }

        $rules['social_links.other.label'] = ['nullable', 'required_if:social_links.other.enabled,1', 'string', 'max:40'];

        $validated = $request->validate($rules);

        $uploadedQrPath = null;
        $uploadedPaths = [];
        $uploadedHeroSlidePaths = [];
        $uploadedSocialIconPaths = [];
        $pathsToDelete = [];

        try {
            $qrCode = $request->file('usdt_qr_code');
            if ($qrCode instanceof UploadedFile) {
                $uploadedQrPath = $this->storePublicImage($qrCode, 'payment-qr');
                $uploadedPaths[] = $uploadedQrPath;
            }

            foreach ($request->file('hero_slides', []) as $heroSlide) {
                $path = $this->storePublicImage($heroSlide, 'homepage/slides');
                $uploadedHeroSlidePaths[] = $path;
                $uploadedPaths[] = $path;
            }

            foreach (['telegram', 'whatsapp', 'facebook', 'instagram', 'other'] as $platform) {
                $icon = $request->file("social_links.{$platform}.icon");
                if ($icon instanceof UploadedFile) {
                    $path = $this->storePublicImage($icon, 'social-icons');
                    $uploadedSocialIconPaths[$platform] = $path;
                    $uploadedPaths[] = $path;
                }
            }

            $homepageBefore = $siteContent->homepageForAdmin();
            $commissionValue = $validated['referral_commission_type'] === 'fixed'
                ? $this->fixedDecimal($validated['referral_commission_fixed_bdt'], 2)
                : $this->fixedDecimal($validated['referral_commission_percent'], 4);

            DB::transaction(function () use (
                $request,
                $validated,
                $commissionValue,
                $uploadedQrPath,
                $uploadedHeroSlidePaths,
                $uploadedSocialIconPaths,
                $homepageBefore,
                $socialLinksBefore,
                $termsBefore,
                &$pathsToDelete,
            ): void {
                foreach ([
                    'platform.annual_fee' => ['value' => $this->fixedDecimal($validated['annual_fee'], 2), 'currency' => 'BDT'],
                    'platform.minimum_withdrawal' => ['value' => $this->fixedDecimal($validated['minimum_withdrawal'], 2), 'currency' => 'BDT'],
                    'platform.referral_commission_percent' => ['value' => $commissionValue, 'currency' => null],
                    'platform.referral_commission_type' => ['value' => $validated['referral_commission_type'], 'currency' => null],
                    'platform.display_currency' => ['value' => $validated['display_currency'], 'currency' => $validated['display_currency']],
                    'platform.payment_instructions.bdt' => ['value' => $validated['bdt_payment_instructions'] ?? '', 'currency' => 'BDT'],
                    'platform.payment_instructions.usdt' => ['value' => $validated['usdt_payment_instructions'] ?? '', 'currency' => 'USDT'],
                ] as $key => $setting) {
                    $version = (int) DB::table('platform_settings')
                        ->where('key', $key)
                        ->lockForUpdate()
                        ->max('version');
                    $nextVersion = $version + 1;
                    $previousValue = $version === 0
                        ? null
                        : DB::table('platform_settings')->where('key', $key)->where('version', $version)->value('value');

                    DB::table('platform_settings')->insert([
                        'key' => $key,
                        'version' => $nextVersion,
                        'value' => $setting['value'],
                        'currency' => $setting['currency'],
                        'effective_at' => now(),
                        'created_by_user_id' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('admin_audit_logs')->insert([
                        'admin_user_id' => $request->user()->id,
                        'action' => 'platform_setting.updated',
                        'subject_type' => 'platform_setting',
                        'subject_id' => null,
                        'before_values' => json_encode([
                            'key' => $key,
                            'value' => $previousValue,
                        ], JSON_THROW_ON_ERROR),
                        'after_values' => json_encode(['key' => $key, 'value' => $setting['value'], 'version' => $nextVersion], JSON_THROW_ON_ERROR),
                        'ip_address' => $request->ip(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($uploadedQrPath !== null) {
                    $this->createSettingVersion($request, 'platform.payment_qr.usdt', $uploadedQrPath, 'USDT');
                }

                $rateVersion = (int) DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->lockForUpdate()
                    ->max('version');
                $nextRateVersion = $rateVersion + 1;
                $rate = $this->fixedDecimal($validated['usdt_to_bdt_rate'], 8);
                $previousRate = $rateVersion === 0
                    ? null
                    : DB::table('exchange_rates')
                        ->where('base_currency', 'USDT')
                        ->where('quote_currency', 'BDT')
                        ->where('version', $rateVersion)
                        ->value('quote_per_base');

                DB::table('exchange_rates')->insert([
                    'base_currency' => 'USDT',
                    'quote_currency' => 'BDT',
                    'quote_per_base' => $rate,
                    'version' => $nextRateVersion,
                    'effective_at' => now(),
                    'created_by_user_id' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('admin_audit_logs')->insert([
                    'admin_user_id' => $request->user()->id,
                    'action' => 'platform_setting.updated',
                    'subject_type' => 'exchange_rate',
                    'subject_id' => null,
                    'before_values' => json_encode([
                        'pair' => 'USDT/BDT',
                        'quote_per_base' => $previousRate,
                    ], JSON_THROW_ON_ERROR),
                    'after_values' => json_encode([
                        'pair' => 'USDT/BDT',
                        'quote_per_base' => $rate,
                        'version' => $nextRateVersion,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (isset($validated['homepage'])) {
                    $homepage = array_replace_recursive($homepageBefore, $validated['homepage']);
                    $existingSlides = array_values(array_filter($homepageBefore['slides'], 'is_string'));
                    $removedSlides = array_values(array_intersect(
                        $existingSlides,
                        $validated['remove_hero_slides'] ?? [],
                    ));
                    $homepage['slides'] = array_values(array_merge(
                        array_diff($existingSlides, $removedSlides),
                        $uploadedHeroSlidePaths,
                    ));

                    $this->createSettingVersion(
                        $request,
                        'platform.homepage_content',
                        json_encode($homepage, JSON_THROW_ON_ERROR),
                        null,
                    );
                    $pathsToDelete = array_merge($pathsToDelete, $removedSlides);
                }

                if (isset($validated['terms']) && $validated['terms'] !== $termsBefore) {
                    $this->createSettingVersion(
                        $request,
                        'platform.terms_and_conditions',
                        json_encode($validated['terms'], JSON_THROW_ON_ERROR),
                        null,
                    );
                }

                if (isset($validated['social_links'])) {
                    $socialLinks = [];

                    foreach (['telegram', 'whatsapp', 'facebook', 'instagram', 'other'] as $platform) {
                        $input = $validated['social_links'][$platform];
                        $currentIconPath = $socialLinksBefore[$platform]['icon_path'];
                        $iconPath = $currentIconPath;

                        if (filter_var($input['remove_icon'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                            $iconPath = null;
                        }

                        if (isset($uploadedSocialIconPaths[$platform])) {
                            $iconPath = $uploadedSocialIconPaths[$platform];
                        }

                        if ($iconPath !== $currentIconPath && $currentIconPath !== null) {
                            $pathsToDelete[] = $currentIconPath;
                        }

                        $socialLinks[$platform] = [
                            'enabled' => (bool) $input['enabled'],
                            'url' => $input['url'] ?? '',
                            'icon_path' => $iconPath,
                        ];

                        if ($platform === 'other') {
                            $socialLinks[$platform]['label'] = $input['label'] ?? '';
                        }
                    }

                    $this->createSettingVersion(
                        $request,
                        'platform.social_links',
                        json_encode($socialLinks, JSON_THROW_ON_ERROR),
                        null,
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($uploadedPaths, $exception);

            throw $exception;
        }

        $this->deleteStoredFiles($pathsToDelete);

        return redirect()->route('admin.settings.edit')->with('status', __('site.admin_settings.saved'));
    }

    private function storePublicImage(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Unable to store an uploaded image in {$directory}.");
        }

        return $path;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredFiles(array $paths, ?Throwable $previous = null): void
    {
        foreach (array_unique($paths) as $path) {
            if (
                Storage::disk('public')->exists($path)
                && ! Storage::disk('public')->delete($path)
            ) {
                report(new RuntimeException("Unable to remove uploaded public file {$path}.", previous: $previous));
            }
        }
    }

    private function currentSetting(string $key, string $default): string
    {
        return (string) (DB::table('platform_settings')
            ->where('key', $key)
            ->orderByDesc('version')
            ->value('value') ?? $default);
    }

    private function fixedDecimal(string|int|float $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (ltrim($integer, '0') ?: '0')
            .'.'.str_pad(substr($fraction, 0, $scale), $scale, '0');
    }

    private function currentExchangeRate(): string
    {
        return (string) (DB::table('exchange_rates')
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'BDT')
            ->orderByDesc('version')
            ->value('quote_per_base') ?? '');
    }

    private function createSettingVersion(Request $request, string $key, string $value, ?string $currency): void
    {
        $version = (int) DB::table('platform_settings')->where('key', $key)->lockForUpdate()->max('version');
        $nextVersion = $version + 1;
        $previousValue = $version === 0
            ? null
            : DB::table('platform_settings')->where('key', $key)->where('version', $version)->value('value');

        DB::table('platform_settings')->insert([
            'key' => $key,
            'version' => $nextVersion,
            'value' => $value,
            'currency' => $currency,
            'effective_at' => now(),
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => 'platform_setting.updated',
            'subject_type' => 'platform_setting',
            'subject_id' => null,
            'before_values' => json_encode(['key' => $key, 'value' => $previousValue], JSON_THROW_ON_ERROR),
            'after_values' => json_encode(['key' => $key, 'value' => $value, 'version' => $nextVersion], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
