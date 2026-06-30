<?php

namespace Tests\Feature\Settings;

use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->office = User::factory()->office()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_office_cannot_open_company_settings(): void
    {
        $this->actingAs($this->office)
            ->get(route('settings.company'))
            ->assertForbidden();
    }

    public function test_admin_can_open_company_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.company'))
            ->assertOk();
    }

    public function test_admin_can_save_profile_and_logo(): void
    {
        Storage::fake('public');
        tenancy()->initialize($this->tenant);
        $this->actingAs($this->admin);

        Volt::test('settings.company')
            ->set('legal_name', 'TTG Inc')
            ->set('website', 'https://ttg.test')
            ->set('tax_number', '12345 RT0001')
            ->set('accent_color', '#0ea5e9')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::current();

        $this->assertSame('TTG Inc', $settings->legal_name);
        $this->assertSame('12345 RT0001', $settings->tax_number);
        $this->assertSame($this->tenant->id, $settings->tenant_id);
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }
}
