<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizationLogoPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_both_theme_logos_as_persistent_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $darkLogo = $this->uploadedLogo('dark-logo.png', 'logo.png');
        $lightLogo = $this->uploadedLogo('light-logo.png', 'logo-light.png');

        $this->actingAs($admin)->post(route('admin.chains.store'), [
            'name' => 'Kalıcı Logolu Zincir',
            'code' => 'KALICI-LOGO',
            'logo' => $darkLogo,
            'logo_light' => $lightLogo,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $organization = Organization::where('code', 'KALICI-LOGO')->firstOrFail();

        $this->assertStringStartsWith('data:image/', $organization->logo_path);
        $this->assertStringStartsWith('data:image/', $organization->logo_light_path);
        $this->assertSame($organization->logo_path, $organization->logo_url);
        $this->assertSame($organization->logo_light_path, $organization->light_logo_url);
    }

    public function test_legacy_logo_paths_still_resolve_as_public_assets(): void
    {
        $organization = Organization::create([
            'name' => 'Eski Logolu Zincir',
            'code' => 'ESKI-LOGO',
            'logo_path' => 'uploads/organizations/dark.png',
            'logo_light_path' => 'uploads/organizations/light.png',
        ]);

        $this->assertStringEndsWith('/uploads/organizations/dark.png', $organization->logo_url);
        $this->assertStringEndsWith('/uploads/organizations/light.png', $organization->light_logo_url);
    }

    private function uploadedLogo(string $temporaryName, string $assetName): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid($temporaryName.'-', true).'.png';
        File::copy(public_path('assets/images/'.$assetName), $path);

        return new UploadedFile($path, $temporaryName, 'image/png', null, true);
    }
}
