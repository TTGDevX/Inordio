<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ItemPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_office_can_upload_an_item_photo(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $item = InventoryItem::factory()->create();

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->set('photo', UploadedFile::fake()->image('pipe.jpg'))
            ->call('savePhoto')
            ->assertHasNoErrors();

        $path = $item->fresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_removing_a_photo_clears_the_column_and_file(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $path = UploadedFile::fake()->image('old.jpg')->store('item-photos/'.$this->tenant->id, 'public');
        $item = InventoryItem::factory()->create(['photo_path' => $path]);

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->call('removePhoto')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_viewer_cannot_upload_a_photo(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $item = InventoryItem::factory()->create();

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->set('photo', UploadedFile::fake()->image('x.jpg'))
            ->call('savePhoto')
            ->assertForbidden();

        $this->assertNull($item->fresh()->photo_path);
    }
}
