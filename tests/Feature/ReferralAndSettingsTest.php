<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferralAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_from_a_valid_referral_link_is_attributed_and_reported(): void
    {
        $referrer = User::factory()->create();
        $referrer->forceFill(['referral_code' => 'WORIREF123'])->save();

        $this->get(route('register', ['ref' => $referrer->referral_code]))->assertOk();
        $this->post(route('register.store'), [
            'name' => 'Referred Investor',
            'email' => 'referred@example.test',
            'password' => 'referred-password-2026',
            'password_confirmation' => 'referred-password-2026',
            'terms_accepted' => '1',
            'terms_version' => '0',
        ])->assertRedirect(route('account.dashboard'));

        $referred = User::query()->where('email', 'referred@example.test')->firstOrFail();
        $this->assertSame($referrer->id, $referred->referred_by_user_id);
        $this->assertNotEmpty($referred->referral_code);
        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(route('register', ['ref' => $referred->referral_code]));

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $this->actingAs($admin)
            ->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee('WORIREF123')
            ->assertSee($referrer->email);
    }

    public function test_signup_can_use_a_manual_referral_code(): void
    {
        $referrer = User::factory()->create();
        $referrer->forceFill(['referral_code' => 'MANUAL1234'])->save();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="referral_code"', false);

        $this->post(route('register.store'), [
            'name' => 'Manually Referred Investor',
            'email' => 'manual-referral@example.test',
            'referral_code' => 'manual1234',
            'password' => 'manual-referral-password-2026',
            'password_confirmation' => 'manual-referral-password-2026',
            'terms_accepted' => '1',
            'terms_version' => '0',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'manual-referral@example.test',
            'referred_by_user_id' => $referrer->id,
        ]);
    }

    public function test_signup_rejects_a_manual_referral_code_from_an_inactive_investor(): void
    {
        $inactiveReferrer = User::factory()->create(['status' => 'suspended']);
        $inactiveReferrer->forceFill(['referral_code' => 'INACTIVE123'])->save();

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Invalid Referral Investor',
                'email' => 'invalid-referral@example.test',
                'referral_code' => 'inactive123',
                'password' => 'invalid-referral-password-2026',
                'password_confirmation' => 'invalid-referral-password-2026',
                'terms_accepted' => '1',
                'terms_version' => '0',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'referral_code' => __('site.auth.referral_code_invalid'),
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'invalid-referral@example.test']);
    }

    public function test_unknown_or_inactive_referrals_are_not_attributed(): void
    {
        $inactive = User::factory()->create(['status' => 'suspended']);
        $inactive->forceFill(['referral_code' => 'INACTIVE123'])->save();

        $this->get(route('register', ['ref' => 'INVALID123']))->assertOk();
        $this->post(route('register.store'), [
            'name' => 'Direct Investor',
            'email' => 'direct@example.test',
            'password' => 'direct-password-2026',
            'password_confirmation' => 'direct-password-2026',
            'terms_accepted' => '1',
            'terms_version' => '0',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'direct@example.test',
            'referred_by_user_id' => null,
        ]);
    }

    public function test_super_admin_settings_are_versioned_and_audited(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Site-wide currency display')
            ->assertSee('Enter how many BDT equal 1 USDT.')
            ->assertSee('Homepage content')
            ->assertSee('Terms and conditions')
            ->assertSee('Community and contact links')
            ->assertSee('Telegram')
            ->assertSee('<details', false)
            ->assertSee('Fixed BDT per confirmed investment');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'USDT',
                'usdt_to_bdt_rate' => '123.45678901',
                'bdt_payment_instructions' => 'Send to the configured BDT account.',
                'usdt_payment_instructions' => 'USDT network: TRC20; enter the verified address.',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.annual_fee',
            'version' => 2,
            'value' => '250.50',
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.minimum_withdrawal',
            'version' => 2,
            'value' => '500.00',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.referral_commission_percent',
            'version' => 2,
            'value' => '4.2500',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.display_currency',
            'version' => 1,
            'value' => 'USDT',
            'currency' => 'USDT',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '123.45678901',
            'version' => 1,
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.payment_instructions.bdt',
            'version' => 1,
            'value' => 'Send to the configured BDT account.',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.payment_instructions.usdt',
            'version' => 1,
            'value' => 'USDT network: TRC20; enter the verified address.',
        ]);
        $this->assertDatabaseCount('admin_audit_logs', 8);
    }

    public function test_super_admin_can_save_a_fixed_referral_commission_and_fixed_amount_is_required(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $settings = [
            'annual_fee' => '0',
            'minimum_withdrawal' => '0',
            'referral_commission_type' => 'fixed',
            'referral_commission_fixed_bdt' => '125.50',
            'display_currency' => 'BDT',
            'usdt_to_bdt_rate' => '120',
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), $settings)
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.referral_commission_type',
            'version' => 1,
            'value' => 'fixed',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.referral_commission_percent',
            'version' => 1,
            'value' => '125.50',
        ]);

        unset($settings['referral_commission_fixed_bdt']);
        $this->actingAs($admin)
            ->put(route('admin.settings.update'), $settings)
            ->assertSessionHasErrors('referral_commission_fixed_bdt');

        $this->assertDatabaseCount('platform_settings', 7);
    }

    public function test_super_admin_can_update_bilingual_terms_used_on_registration(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $terms = [
            'en' => "Updated English terms.\nReview each project's current information.",
            'bn' => 'হালনাগাদ বাংলা শর্তাবলি।',
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '0',
                'minimum_withdrawal' => '0',
                'referral_commission_percent' => '0',
                'display_currency' => 'BDT',
                'usdt_to_bdt_rate' => '120',
                'terms' => $terms,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.terms_and_conditions',
            'version' => 1,
            'value' => json_encode($terms, JSON_THROW_ON_ERROR),
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'platform_setting.updated',
            'before_values' => json_encode([
                'key' => 'platform.terms_and_conditions',
                'value' => null,
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Updated English terms.')
            ->assertSee('name="terms_version" value="1"', false);

        $this->withSession(['locale' => 'bn'])
            ->get(route('register'))
            ->assertOk()
            ->assertSee('হালনাগাদ বাংলা শর্তাবলি।');
    }

    public function test_admin_settings_escape_terms_content_on_the_registration_page(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '0',
                'minimum_withdrawal' => '0',
                'referral_commission_percent' => '0',
                'display_currency' => 'BDT',
                'usdt_to_bdt_rate' => '120',
                'terms' => [
                    'en' => '<script>alert("xss")</script>',
                    'bn' => 'নিরাপদ শর্তাবলি।',
                ],
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_super_admin_can_publish_localized_homepage_content_and_social_links(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $socialLinks = [
            'telegram' => [
                'enabled' => '1',
                'url' => 'https://t.me/worifinance',
                'icon' => UploadedFile::fake()->image('telegram.png'),
            ],
            'whatsapp' => ['enabled' => '0', 'url' => 'https://wa.me/disabled'],
            'facebook' => ['enabled' => '0', 'url' => ''],
            'instagram' => ['enabled' => '0', 'url' => ''],
            'other' => ['enabled' => '0', 'url' => '', 'label' => ''],
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'BDT',
                'usdt_to_bdt_rate' => '123.45678901',
                'homepage' => [
                    'hero' => [
                        'title_before' => [
                            'en' => 'Build your future',
                            'bn' => 'আপনার ভবিষ্যৎ গড়ুন',
                        ],
                    ],
                    'about' => [
                        'title' => [
                            'en' => 'A clearer way to invest',
                            'bn' => 'বিনিয়োগের আরও স্বচ্ছ পথ',
                        ],
                    ],
                    'how_it_works' => [
                        'step_1_title' => [
                            'en' => 'Review every detail',
                            'bn' => 'প্রতিটি বিবরণ পর্যালোচনা করুন',
                        ],
                    ],
                ],
                'hero_slides' => [UploadedFile::fake()->image('homepage-banner.jpg', 1400, 800)],
                'social_links' => $socialLinks,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $storedHomepage = json_decode(
            DB::table('platform_settings')->where('key', 'platform.homepage_content')->value('value'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $storedSocialLinks = json_decode(
            DB::table('platform_settings')->where('key', 'platform.social_links')->value('value'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Build your future', $storedHomepage['hero']['title_before']['en']);
        $this->assertSame('আপনার ভবিষ্যৎ গড়ুন', $storedHomepage['hero']['title_before']['bn']);
        $this->assertSame(1, count($storedHomepage['slides']));
        $this->assertTrue($storedSocialLinks['telegram']['enabled']);
        $this->assertSame('https://t.me/worifinance', $storedSocialLinks['telegram']['url']);
        $this->assertNotEmpty($storedSocialLinks['telegram']['icon_path']);
        Storage::disk('public')->assertExists($storedHomepage['slides'][0]);
        Storage::disk('public')->assertExists($storedSocialLinks['telegram']['icon_path']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Build your future')
            ->assertSee('A clearer way to invest')
            ->assertSee('Review every detail')
            ->assertSee('https://t.me/worifinance', false)
            ->assertDontSee('https://wa.me/');

        $this->post(route('language.update'), ['locale' => 'bn'])->assertRedirect(route('home'));
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('আপনার ভবিষ্যৎ গড়ুন')
            ->assertSee('বিনিয়োগের আরও স্বচ্ছ পথ')
            ->assertSee('প্রতিটি বিবরণ পর্যালোচনা করুন');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'BDT',
                'usdt_to_bdt_rate' => '123.45678901',
                'homepage' => [
                    'hero' => [
                        'caption' => ['en' => 'Updated caption', 'bn' => 'আপডেট ক্যাপশন'],
                    ],
                ],
                'remove_hero_slides' => [$storedHomepage['slides'][0]],
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $updatedHomepage = json_decode(
            DB::table('platform_settings')->where('key', 'platform.homepage_content')->orderByDesc('version')->value('value'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertSame([], $updatedHomepage['slides']);
        Storage::disk('public')->assertMissing($storedHomepage['slides'][0]);
    }

    public function test_social_links_reject_non_web_urls_without_saving_settings(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $socialLinks = [
            'telegram' => ['enabled' => '1', 'url' => 'javascript:alert(1)'],
            'whatsapp' => ['enabled' => '0', 'url' => ''],
            'facebook' => ['enabled' => '0', 'url' => ''],
            'instagram' => ['enabled' => '0', 'url' => ''],
            'other' => ['enabled' => '0', 'url' => '', 'label' => ''],
        ];

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'BDT',
                'usdt_to_bdt_rate' => '123.45678901',
                'social_links' => $socialLinks,
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors(['social_links.telegram.url', 'social_links.telegram.icon']);

        $this->assertDatabaseMissing('platform_settings', ['key' => 'platform.social_links']);
    }

    public function test_invalid_site_currency_or_exchange_rate_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'USD',
                'usdt_to_bdt_rate' => '0',
            ])
            ->assertSessionHasErrors(['display_currency', 'usdt_to_bdt_rate']);

        $this->assertDatabaseCount('exchange_rates', 0);
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_investor_can_open_referral_dashboard_without_any_activity(): void
    {
        $investor = User::factory()->create();
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        $this->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('Referral program')
            ->assertSee('No accounts have registered through your referral link yet.');

        $this->assertSame(1, DB::table('users')->whereNotNull('referral_code')->count());
    }
}
