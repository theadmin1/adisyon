<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeliveryOrder;
use App\Models\DiningTable;
use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_user_can_log_in_and_see_only_organization_branches(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $ownBranch = Branch::create(['name' => 'Kadıköy', 'code' => 'KDK-01', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Başka Zincir', 'code' => 'BSK-01', 'is_active' => true]);
        $organization->branches()->attach($ownBranch);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
            'is_admin' => false,
        ]);

        $this->post(route('chain.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('chain.dashboard'));

        $this->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('Yedi Günlük Satış Trendi')
            ->assertSee('Ödeme Dağılımı')
            ->assertSee('Kadıköy')
            ->assertDontSee('Başka Zincir');
    }

    public function test_branch_assignment_limits_a_regional_chain_user(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $first = Branch::create(['name' => 'Avrupa Şubesi', 'code' => 'AVR-01']);
        $second = Branch::create(['name' => 'Anadolu Şubesi', 'code' => 'AND-01']);
        $organization->branches()->attach([$first->id, $second->id]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'regional_manager',
            'branch_id' => null,
        ]);
        $user->chainBranches()->attach($second);

        $this->actingAs($user)->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('Anadolu Şubesi')
            ->assertDontSee('Avrupa Şubesi');
    }

    public function test_dashboard_totals_exclude_another_organization_sales(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $ownBranch = Branch::create(['name' => 'Merkez', 'code' => 'MRK-01']);
        $otherBranch = Branch::create(['name' => 'Harici', 'code' => 'HRC-01']);
        $organization->branches()->attach($ownBranch);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
        ]);

        Check::create([
            'branch_id' => $ownBranch->id,
            'check_number' => 'OWN-1',
            'status' => 'closed',
            'total' => 125,
            'closed_at' => now(),
        ]);
        Check::create([
            'branch_id' => $otherBranch->id,
            'check_number' => 'OTHER-1',
            'status' => 'closed',
            'total' => 999,
            'closed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('₺125,00')
            ->assertDontSee('₺999,00');
    }

    public function test_restaurant_user_cannot_access_chain_dashboard(): void
    {
        $restaurantUser = User::factory()->create(['organization_id' => null, 'chain_role' => null]);

        $this->actingAs($restaurantUser)->get(route('chain.dashboard'))
            ->assertRedirect(route('chain.login'));
    }

    public function test_chain_logo_is_inherited_by_restaurant_and_chain_panels(): void
    {
        $organization = Organization::create([
            'name' => 'Markalı Zincir',
            'code' => 'BRAND',
            'logo_path' => 'uploads/organizations/brand-logo.png',
            'logo_light_path' => 'uploads/organizations/brand-logo-light.png',
        ]);
        $branch = Branch::create(['name' => 'Markalı Şube', 'code' => 'BRAND-01', 'is_active' => true]);
        $organization->branches()->attach($branch);

        $restaurantUser = User::factory()->create([
            'branch_id' => $branch->id,
            'restaurant_id' => 'BRAND-01',
            'is_admin' => false,
        ]);
        $chainUser = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
            'is_admin' => false,
        ]);

        $this->actingAs($restaurantUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('uploads/organizations/brand-logo.png')
            ->assertSee('uploads/organizations/brand-logo-light.png');

        $this->actingAs($chainUser)->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('uploads/organizations/brand-logo.png')
            ->assertSee('uploads/organizations/brand-logo-light.png');
    }

    public function test_admin_can_create_chain_user_with_selected_branch_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organization = Organization::create(['name' => 'Yeni Zincir', 'code' => 'YENI']);
        $first = Branch::create(['name' => 'Birinci', 'code' => 'BIR-01']);
        $second = Branch::create(['name' => 'İkinci', 'code' => 'IKI-01']);
        $organization->branches()->attach([$first->id, $second->id]);

        $this->actingAs($admin)->post(route('admin.chain-users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Bölge Müdürü',
            'email' => 'bolge@example.com',
            'password' => 'StrongPass!123',
            'chain_role' => 'regional_manager',
            'branch_ids' => [$second->id],
        ])->assertRedirect();

        $user = User::where('email', 'bolge@example.com')->firstOrFail();
        $this->assertSame([$second->id], $user->accessibleChainBranchIds());
    }

    public function test_admin_cannot_assign_another_chains_branch_to_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organization = Organization::create(['name' => 'Birinci Zincir', 'code' => 'BIR']);
        $foreignBranch = Branch::create(['name' => 'Yabancı Şube', 'code' => 'YBN-01']);

        $this->actingAs($admin)->post(route('admin.chain-users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Yetkisiz Atama',
            'email' => 'invalid@example.com',
            'password' => 'StrongPass!123',
            'chain_role' => 'regional_manager',
            'branch_ids' => [$foreignBranch->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.com']);
    }

    public function test_chain_report_rejects_inaccessible_branch_filter(): void
    {
        $organization = Organization::create(['name' => 'Rapor Zinciri', 'code' => 'RAPOR']);
        $ownBranch = Branch::create(['name' => 'Yetkili', 'code' => 'YET-01']);
        $foreignBranch = Branch::create(['name' => 'Yetkisiz', 'code' => 'YZ-01']);
        $organization->branches()->attach($ownBranch);
        $user = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'analyst', 'branch_id' => null]);

        $this->actingAs($user)->get(route('chain.reports.index', ['branch_id' => $foreignBranch->id]))
            ->assertForbidden();
    }

    public function test_chain_report_shows_product_performance_and_estimated_profit(): void
    {
        [$organization,$branch,$owner,$product,$supplier]=$this->purchasingFixture();
        $order=PurchaseOrder::withoutGlobalScopes()->create(['branch_id'=>$branch->id,'supplier_id'=>$supplier->id,'created_by_user_id'=>$owner->id,'order_number'=>'SAT-RAPOR-1','status'=>'received','created_by_name'=>$owner->name,'order_date'=>now(),'subtotal'=>100,'tax_total'=>0,'total'=>100]);
        $order->items()->create(['branch_id'=>$branch->id,'product_id'=>$product->id,'product_name'=>$product->name,'unit'=>'adet','quantity'=>10,'received_quantity'=>10,'unit_price'=>10,'tax_rate'=>0,'line_subtotal'=>100,'line_tax'=>0,'line_total'=>100]);
        $check=Check::create(['branch_id'=>$branch->id,'check_number'=>'RAPOR-1','status'=>'closed','subtotal'=>100,'total'=>100,'guest_count'=>2,'opened_at'=>now()->subHour(),'closed_at'=>now()]);
        CheckItem::create(['branch_id'=>$branch->id,'check_id'=>$check->id,'product_id'=>$product->id,'product_name'=>$product->name,'unit_price'=>50,'quantity'=>2,'total_price'=>100]);

        $this->actingAs($owner)->get(route('chain.reports.index'))
            ->assertOk()
            ->assertSee('Kahve')
            ->assertSee('₺80,00')
            ->assertSee('80,0%')
            ->assertSee('Günlük Satış Grafiği')
            ->assertSee('executive-donut', false);
    }

    public function test_chain_report_center_exposes_every_pos_module(): void
    {
        [$organization,$branch,$owner,$product,$supplier]=$this->purchasingFixture();
        DiningTable::create(['branch_id'=>$branch->id,'name'=>'Masa 1','code'=>'M1','capacity'=>4,'status'=>'available','is_active'=>true]);
        DeliveryOrder::withoutGlobalScopes()->create(['branch_id'=>$branch->id,'channel'=>'phone','order_number'=>'PKT-RAPOR-1','customer_name'=>'Rapor Müşterisi','customer_phone'=>'5550000000','delivery_address'=>'Test adresi','status'=>'delivered','subtotal'=>200,'total'=>200,'items'=>[['name'=>'Kahve','quantity'=>2]],'received_at'=>now()->subMinutes(30),'delivered_at'=>now()]);

        $expected = [
            'overview'=>'Şube Karşılaştırması', 'tables'=>'En Yoğun Masalar', 'quick_sale'=>'Şube Bazlı Hızlı Satış',
            'delivery'=>'Kanal Performansı', 'kitchen'=>'Mutfakta En Çok İşlenen Ürünler', 'products'=>'Ürün Satış ve Kârlılık Performansı',
            'stocks'=>'Merkez Depo Kritik Listesi', 'production'=>'Üretim ve Reçete Performansı', 'purchasing'=>'Tedarikçi Performansı',
        ];
        foreach ($expected as $module=>$heading) {
            $this->actingAs($owner)->get(route('chain.reports.index',['module'=>$module]))->assertOk()->assertSee($heading);
        }
    }

    public function test_chain_report_rejects_unknown_module(): void
    {
        $organization=Organization::create(['name'=>'Rapor Modül Zinciri','code'=>'RMZ']);
        $branch=Branch::create(['name'=>'Rapor Şubesi','code'=>'RMS-01']); $organization->branches()->attach($branch);
        $owner=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'owner','branch_id'=>null]);
        $this->actingAs($owner)->get(route('chain.reports.index',['module'=>'admin']))->assertSessionHasErrors('module');
    }

    public function test_chain_owner_can_publish_central_product_with_branch_price_override(): void
    {
        $organization = Organization::create(['name' => 'Menü Zinciri', 'code' => 'MENU']);
        $first = Branch::create(['name' => 'Merkez', 'code' => 'MENU-01']);
        $second = Branch::create(['name' => 'Sahil', 'code' => 'MENU-02']);
        $organization->branches()->attach([$first->id, $second->id]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'Burger', 'slug' => 'burger']);

        $this->actingAs($owner)->post(route('chain.menu.products.store'), [
            'chain_menu_category_id' => $category->id,
            'name' => 'Merkez Burger',
            'sku' => 'BRG-100',
            'base_price' => 250,
            'branch_ids' => [$first->id, $second->id],
            'enabled_branch_ids' => [$first->id],
            'price_overrides' => [$second->id => 275],
            'is_active' => 1,
        ])->assertRedirect();

        $centralProduct = ChainMenuProduct::where('sku', 'BRG-100')->firstOrFail();
        $this->actingAs($owner)->post(route('chain.menu.products.publish', $centralProduct), [
            'branch_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['branch_id' => $first->id, 'sku' => 'BRG-100', 'price' => 250, 'is_active' => true]);
        $this->assertDatabaseHas('products', ['branch_id' => $second->id, 'sku' => 'BRG-100', 'price' => 275, 'is_active' => false]);
        $this->assertSame(2, Category::withoutGlobalScopes()->where('slug', 'burger')->count());
    }

    public function test_chain_owner_can_upload_a_central_menu_product_image(): void
    {
        $organization = Organization::create(['name' => 'Görselli Menü', 'code' => 'IMAGE']);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'Çorba', 'slug' => 'corba']);
        $temporaryImage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menu-image-'.uniqid().'.webp';
        File::copy(public_path('assets/images/soups/mercimek.webp'), $temporaryImage);

        $this->actingAs($owner)->post(route('chain.menu.products.store'), [
            'chain_menu_category_id' => $category->id,
            'name' => 'Mercimek Çorbası',
            'sku' => 'COR-IMG-1',
            'base_price' => 110,
            'image_file' => new UploadedFile($temporaryImage, 'mercimek.webp', 'image/webp', null, true),
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = ChainMenuProduct::where('sku', 'COR-IMG-1')->firstOrFail();
        $this->assertStringStartsWith('uploads/chain-menu/', $product->image_path);
        $this->assertFileExists(public_path($product->image_path));
        $this->actingAs($owner)->get(route('chain.menu.index'))->assertOk()->assertSee($product->image_path);

        File::delete(public_path($product->image_path));
    }

    public function test_chain_owner_can_publish_raw_material_with_stock_unit_without_selling_it(): void
    {
        $organization = Organization::create(['name' => 'F&B Zinciri', 'code' => 'FBTEST']);
        $branch = Branch::create(['name' => 'Mutfak', 'code' => 'FB-01']);
        $organization->branches()->attach($branch);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'F&B Stok Deposu', 'slug' => 'fb-stok-deposu']);

        $this->actingAs($owner)->post(route('chain.menu.products.store'), [
            'chain_menu_category_id' => $category->id,
            'name' => 'Dana Kıyma',
            'sku' => 'FB-ET-001',
            'base_price' => 0,
            'unit' => 'kg',
            'item_type' => 'raw_material',
            'track_stock' => 1,
            'branch_ids' => [$branch->id],
            'enabled_branch_ids' => [$branch->id],
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $centralProduct = ChainMenuProduct::where('sku', 'FB-ET-001')->firstOrFail();
        $this->actingAs($owner)->post(route('chain.menu.products.publish', $centralProduct), [
            'branch_ids' => [$branch->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'branch_id' => $branch->id,
            'sku' => 'FB-ET-001',
            'unit' => 'kg',
            'track_stock' => true,
            'is_active' => false,
        ]);
    }

    public function test_analyst_cannot_change_central_menu(): void
    {
        $organization = Organization::create(['name' => 'Salt Okunur', 'code' => 'READ']);
        $analyst = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'analyst', 'branch_id' => null]);

        $this->actingAs($analyst)->post(route('chain.menu.categories.store'), ['name' => 'Yetkisiz'])
            ->assertForbidden();
        $this->assertDatabaseMissing('chain_menu_categories', ['name' => 'Yetkisiz']);
    }

    public function test_chain_cannot_publish_another_organizations_product(): void
    {
        $firstOrganization = Organization::create(['name' => 'Birinci', 'code' => 'ORG1']);
        $secondOrganization = Organization::create(['name' => 'İkinci', 'code' => 'ORG2']);
        $owner = User::factory()->create(['organization_id' => $firstOrganization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $secondOrganization->id, 'name' => 'İçecek', 'slug' => 'icecek']);
        $product = ChainMenuProduct::create(['organization_id' => $secondOrganization->id, 'chain_menu_category_id' => $category->id, 'name' => 'Kola', 'sku' => 'DRK-1', 'base_price' => 50]);

        $this->actingAs($owner)->post(route('chain.menu.products.publish', $product), ['branch_ids' => [999]])
            ->assertNotFound();
    }

    public function test_chain_workflow_recipe_uses_central_menu_products(): void
    {
        $organization = Organization::create(['name' => 'Merkezi Reçete Zinciri', 'code' => 'MRZ']);
        $branch = Branch::create(['name' => 'Üretim Şubesi', 'code' => 'URT-01']);
        $organization->branches()->attach($branch);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $menuCategory = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'Ana Yemekler', 'slug' => 'ana-yemekler']);
        $stockCategory = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'F&B Stok Deposu', 'slug' => 'fb-stok-deposu']);
        $centralOutput = ChainMenuProduct::create([
            'organization_id' => $organization->id, 'chain_menu_category_id' => $menuCategory->id,
            'name' => 'Kuru Fasulye', 'sku' => 'ANA-001', 'base_price' => 220, 'unit' => 'porsiyon',
            'item_type' => 'menu_item', 'track_stock' => true, 'is_active' => true,
        ]);
        $centralIngredient = ChainMenuProduct::create([
            'organization_id' => $organization->id, 'chain_menu_category_id' => $stockCategory->id,
            'name' => 'Kuru Fasulye Hammaddesi', 'sku' => 'FB-001', 'base_price' => 0, 'unit' => 'kg',
            'item_type' => 'raw_material', 'track_stock' => true, 'is_active' => true,
        ]);
        $centralLiquid = ChainMenuProduct::create([
            'organization_id' => $organization->id, 'chain_menu_category_id' => $stockCategory->id,
            'name' => 'Ayçiçek Yağı', 'sku' => 'FB-002', 'base_price' => 0, 'unit' => 'l',
            'item_type' => 'raw_material', 'track_stock' => true, 'is_active' => true,
        ]);
        ChainMenuProduct::create([
            'organization_id' => $organization->id, 'chain_menu_category_id' => $menuCategory->id,
            'name' => 'Pasif Merkezi Ürün', 'sku' => 'ANA-002', 'base_price' => 100,
            'item_type' => 'menu_item', 'track_stock' => false, 'is_active' => false,
        ]);
        $localCategory = Category::create(['branch_id' => $branch->id, 'name' => 'Yerel', 'slug' => 'yerel']);
        Product::create(['branch_id' => $branch->id, 'category_id' => $localCategory->id, 'name' => 'Sadece Şubede', 'slug' => 'sadece-subede', 'sku' => 'LOCAL-1', 'price' => 10, 'is_active' => true]);

        $this->actingAs($owner)->get(route('chain.workflows.index'))
            ->assertOk()->assertSee('Kuru Fasulye')->assertSee('Kuru Fasulye Hammaddesi')->assertSee('Pasif Merkezi Ürün')
            ->assertDontSee('Sadece Şubede')
            ->assertViewHas('centralCategories', fn ($categories) => $categories->count() === 2)
            ->assertViewHas('centralProducts', fn ($products) => $products->count() === 4)
            ->assertViewHas('centralIngredients', fn ($ingredients) => $ingredients->count() === 2);

        $this->actingAs($owner)->post(route('chain.workflows.recipes.store'), [
            'branch_id' => $branch->id, 'name' => 'Hatalı Birim Reçetesi',
            'output_menu_product_id' => $centralOutput->id, 'base_servings' => 10,
            'items' => [['menu_product_id' => $centralLiquid->id, 'quantity' => 40, 'unit' => 'g']],
        ])->assertRedirect()->assertSessionHasErrors('items.0.unit');
        $this->assertDatabaseMissing('production_recipes', ['name' => 'Hatalı Birim Reçetesi']);

        $this->actingAs($owner)->post(route('chain.workflows.recipes.store'), [
            'branch_id' => $branch->id, 'name' => 'Merkezi Kuru Fasulye',
            'output_menu_product_id' => $centralOutput->id, 'base_servings' => 10,
            'items' => [['menu_product_id' => $centralIngredient->id, 'quantity' => 1.5, 'unit' => 'kg']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $outputProduct = Product::withoutGlobalScopes()->where('branch_id', $branch->id)->where('sku', 'ANA-001')->firstOrFail();
        $ingredientProduct = Product::withoutGlobalScopes()->where('branch_id', $branch->id)->where('sku', 'FB-001')->firstOrFail();
        $recipe = ProductionRecipe::withoutGlobalScopes()->with('items')->firstOrFail();
        $this->assertSame($outputProduct->id, $recipe->output_product_id);
        $this->assertSame($ingredientProduct->id, $recipe->items->first()->ingredient_product_id);
        $this->assertSame(0.0, (float) $ingredientProduct->stock_quantity);
        $this->assertFalse((bool) $ingredientProduct->is_active);
        $this->assertDatabaseHas('chain_menu_product_branch', ['chain_menu_product_id' => $centralOutput->id, 'branch_id' => $branch->id, 'published_product_id' => $outputProduct->id]);
        $this->assertDatabaseHas('chain_menu_product_branch', ['chain_menu_product_id' => $centralIngredient->id, 'branch_id' => $branch->id, 'published_product_id' => $ingredientProduct->id]);
    }

    public function test_stock_transfer_moves_quantity_exactly_once_between_branches(): void
    {
        [$organization,$source,$target,$owner,$sourceProduct,$targetProduct]=$this->stockTransferFixture();
        $service=app(StockTransferService::class);
        $transfer=$service->create($owner,$source->id,$target->id,[['product_id'=>$sourceProduct->id,'quantity'=>10]],'Test');
        $service->approve($transfer,$owner);
        $this->assertSame(90.0,(float)$sourceProduct->fresh()->stock_quantity);
        $service->ship($transfer->fresh(),$owner);
        $service->receive($transfer->fresh(),$owner);
        $this->assertSame(30.0,(float)$targetProduct->fresh()->stock_quantity);
        $this->assertSame('received',$transfer->fresh()->status);
        $this->assertDatabaseHas('stock_movements',['product_id'=>$sourceProduct->id,'type'=>'transfer_out','quantity'=>10]);
        $this->assertDatabaseHas('stock_movements',['product_id'=>$targetProduct->id,'type'=>'transfer_in','quantity'=>10]);
    }

    public function test_cancelling_approved_transfer_restores_source_stock(): void
    {
        [$organization,$source,$target,$owner,$sourceProduct]=$this->stockTransferFixture();
        $service=app(StockTransferService::class);
        $transfer=$service->create($owner,$source->id,$target->id,[['product_id'=>$sourceProduct->id,'quantity'=>15]],null);
        $service->approve($transfer,$owner); $service->cancel($transfer->fresh(),$owner);
        $this->assertSame(100.0,(float)$sourceProduct->fresh()->stock_quantity);
        $this->assertSame('cancelled',$transfer->fresh()->status);
    }

    public function test_insufficient_stock_rolls_back_transfer_approval(): void
    {
        [$organization,$source,$target,$owner,$sourceProduct]=$this->stockTransferFixture();
        $service=app(StockTransferService::class);
        $transfer=$service->create($owner,$source->id,$target->id,[['product_id'=>$sourceProduct->id,'quantity'=>101]],null);
        try { $service->approve($transfer,$owner); $this->fail('ValidationException bekleniyordu.'); } catch (ValidationException) {}
        $this->assertSame(100.0,(float)$sourceProduct->fresh()->stock_quantity);
        $this->assertSame('requested',$transfer->fresh()->status);
    }

    public function test_analyst_cannot_create_stock_transfer(): void
    {
        [$organization,$source,$target,$owner,$sourceProduct]=$this->stockTransferFixture();
        $analyst=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'analyst','branch_id'=>null]);
        $this->actingAs($analyst)->post(route('chain.stock-transfers.store'),['source_branch_id'=>$source->id,'target_branch_id'=>$target->id,'items'=>[['product_id'=>$sourceProduct->id,'quantity'=>1]]])->assertForbidden();
        $this->assertDatabaseCount('stock_transfers',0);
    }

    public function test_chain_owner_can_adjust_branch_stock_from_central_panel(): void
    {
        [$organization,$source,$target,$owner,$product]=$this->stockTransferFixture();

        $this->actingAs($owner)->post(route('chain.stocks.adjust'),[
            'branch_id'=>$source->id,'product_id'=>$product->id,'operation'=>'add','quantity'=>12.5,
            'min_stock_level'=>15,'notes'=>'Mal kabulü',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(112.5,(float)$product->fresh()->stock_quantity);
        $this->assertSame(15.0,(float)$product->fresh()->min_stock_level);
        $this->assertDatabaseHas('stock_movements',['product_id'=>$product->id,'type'=>'manual_addition','quantity'=>12.5,'approved_by_user_id'=>$owner->id]);

        $this->actingAs($owner)->post(route('chain.stocks.adjust'),[
            'branch_id'=>$source->id,'product_id'=>$product->id,'operation'=>'subtract','quantity'=>2.5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(110.0,(float)$product->fresh()->stock_quantity);

        $this->actingAs($owner)->post(route('chain.stocks.adjust'),[
            'branch_id'=>$source->id,'product_id'=>$product->id,'operation'=>'set','quantity'=>35,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(35.0,(float)$product->fresh()->stock_quantity);
    }

    public function test_chain_owner_can_receive_central_stock_and_distribute_it_to_branches(): void
    {
        [$organization,$source,$target,$owner]=$this->stockTransferFixture();
        $centralCategory=ChainMenuCategory::create(['organization_id'=>$organization->id,'name'=>'F&B Stok Deposu','slug'=>'fb-stok-deposu']);
        $centralProduct=ChainMenuProduct::create([
            'organization_id'=>$organization->id,'chain_menu_category_id'=>$centralCategory->id,
            'name'=>'Dana Kıyma','sku'=>'FB-ET-001','base_price'=>0,'unit'=>'kg','item_type'=>'raw_material',
            'track_stock'=>true,'stock_quantity'=>100,'min_stock_level'=>20,'is_active'=>true,
        ]);

        $this->actingAs($owner)->post(route('chain.stocks.central.adjust'),[
            'product_id'=>$centralProduct->id,'operation'=>'add','quantity'=>50,'notes'=>'Mal kabulü',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(150.0,(float)$centralProduct->fresh()->stock_quantity);
        $this->assertDatabaseHas('chain_inventory_movements',['chain_menu_product_id'=>$centralProduct->id,'type'=>'central_addition','quantity'=>50]);

        $this->actingAs($owner)->post(route('chain.stocks.central.distribute'),[
            'product_id'=>$centralProduct->id,'allocations'=>[
                ['branch_id'=>$source->id,'quantity'=>40],['branch_id'=>$target->id,'quantity'=>35],
            ],'notes'=>'Haftalık dağıtım',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(75.0,(float)$centralProduct->fresh()->stock_quantity);
        $this->assertDatabaseHas('products',['branch_id'=>$source->id,'sku'=>'FB-ET-001','stock_quantity'=>40,'unit'=>'kg','track_stock'=>true,'is_active'=>false]);
        $this->assertDatabaseHas('products',['branch_id'=>$target->id,'sku'=>'FB-ET-001','stock_quantity'=>35,'unit'=>'kg','track_stock'=>true,'is_active'=>false]);
        $this->assertDatabaseCount('chain_inventory_movements',3);
        $this->assertDatabaseHas('stock_movements',['type'=>'central_distribution','quantity'=>40]);
    }

    public function test_central_distribution_cannot_exceed_available_stock(): void
    {
        [$organization,$source,$target,$owner]=$this->stockTransferFixture();
        $category=ChainMenuCategory::create(['organization_id'=>$organization->id,'name'=>'F&B Depo','slug'=>'fb-depo']);
        $product=ChainMenuProduct::create(['organization_id'=>$organization->id,'chain_menu_category_id'=>$category->id,'name'=>'Süt','sku'=>'FB-SUT-1','base_price'=>0,'unit'=>'l','item_type'=>'raw_material','track_stock'=>true,'stock_quantity'=>10]);

        $this->actingAs($owner)->from(route('chain.stocks.index'))->post(route('chain.stocks.central.distribute'),[
            'product_id'=>$product->id,'allocations'=>[['branch_id'=>$source->id,'quantity'=>11]],
        ])->assertRedirect(route('chain.stocks.index'))->assertSessionHasErrors('allocations');
        $this->assertSame(10.0,(float)$product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('products',['branch_id'=>$source->id,'sku'=>'FB-SUT-1']);
    }

    public function test_analyst_cannot_adjust_branch_stock(): void
    {
        [$organization,$source,$target,$owner,$product]=$this->stockTransferFixture();
        $analyst=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'analyst','branch_id'=>null]);
        $this->actingAs($analyst)->post(route('chain.stocks.adjust'),[
            'branch_id'=>$source->id,'product_id'=>$product->id,'operation'=>'add','quantity'=>10,
        ])->assertForbidden();
        $this->assertSame(100.0,(float)$product->fresh()->stock_quantity);
    }

    public function test_chain_owner_can_create_and_receive_central_warehouse_purchase_order(): void
    {
        [$organization,$branch,$owner,$product,$supplier]=$this->purchasingFixture();
        $centralCategory=ChainMenuCategory::create(['organization_id'=>$organization->id,'name'=>'F&B Depo','slug'=>'fb-depo-satin-alma']);
        $centralProduct=ChainMenuProduct::create(['organization_id'=>$organization->id,'chain_menu_category_id'=>$centralCategory->id,'name'=>'Kahve Çekirdeği','sku'=>'FB-KHV-1','base_price'=>0,'unit'=>'kg','item_type'=>'raw_material','track_stock'=>true,'stock_quantity'=>5,'is_active'=>true]);
        $this->actingAs($owner)->post(route('chain.purchasing.orders.store'),[
            'branch_id'=>$branch->id,'supplier_id'=>$supplier->id,'order_date'=>now()->toDateString(),
            'items'=>[['chain_menu_product_id'=>$centralProduct->id,'quantity'=>12,'unit_price'=>25,'tax_rate'=>20]],
        ])->assertRedirect();
        $order=PurchaseOrder::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(360.0,(float)$order->total);
        $this->assertSame('central',$order->inventory_destination);
        $this->assertSame($organization->id,$order->organization_id);
        $this->actingAs($owner)->get(route('chain.purchasing.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($owner)->post(route('chain.purchasing.orders.place',$order->id))->assertRedirect();
        $item=$order->items()->firstOrFail();
        $this->actingAs($owner)->post(route('chain.purchasing.orders.receive',$order->id),['quantities'=>[$item->id=>12]])->assertRedirect();
        $this->assertSame(17.0,(float)$centralProduct->fresh()->stock_quantity);
        $this->assertSame(5.0,(float)$product->fresh()->stock_quantity);
        $this->assertSame('received',$order->fresh()->status);
        $this->assertDatabaseHas('chain_inventory_movements',['chain_menu_product_id'=>$centralProduct->id,'type'=>'purchase_receipt','quantity'=>12,'stock_after'=>17]);
        $this->assertDatabaseHas('purchase_receipt_items',['chain_menu_product_id'=>$centralProduct->id,'quantity'=>12]);
    }

    public function test_regional_manager_cannot_purchase_for_inaccessible_branch(): void
    {
        [$organization,$branch,$owner,$product,$supplier]=$this->purchasingFixture();
        $other=Branch::create(['name'=>'Yetkili Şube','code'=>'AUTH'.fake()->unique()->numberBetween(100,999)]);
        $organization->branches()->attach($other); $manager=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'regional_manager','branch_id'=>null]); $manager->chainBranches()->attach($other);
        $this->actingAs($manager)->post(route('chain.purchasing.orders.store'),['branch_id'=>$branch->id,'supplier_id'=>$supplier->id,'order_date'=>now()->toDateString(),'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>1]]])->assertForbidden();
        $this->assertDatabaseCount('purchase_orders',0);
    }

    public function test_analyst_cannot_create_chain_supplier(): void
    {
        [$organization,$branch]=$this->purchasingFixture();
        $analyst=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'analyst','branch_id'=>null]);
        $this->actingAs($analyst)->post(route('chain.purchasing.suppliers.store'),['branch_id'=>$branch->id,'name'=>'Yetkisiz Tedarikçi'])->assertForbidden();
        $this->assertDatabaseMissing('suppliers',['name'=>'Yetkisiz Tedarikçi']);
    }

    private function purchasingFixture(): array
    {
        $organization=Organization::create(['name'=>'Satın Alma Zinciri','code'=>'BUY'.fake()->unique()->numberBetween(100,999)]);
        $branch=Branch::create(['name'=>'Satın Alma Şubesi','code'=>'BUYB'.fake()->unique()->numberBetween(100,999)]); $organization->branches()->attach($branch);
        $owner=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'owner','branch_id'=>null]);
        $category=Category::create(['branch_id'=>$branch->id,'name'=>'Hammadde','slug'=>'hammadde-'.fake()->unique()->numberBetween(100,999)]);
        $product=Product::create(['branch_id'=>$branch->id,'category_id'=>$category->id,'name'=>'Kahve','slug'=>'kahve','sku'=>'KHV-1','price'=>50,'stock_quantity'=>5,'track_stock'=>true]);
        $supplier=Supplier::withoutGlobalScopes()->create(['branch_id'=>$branch->id,'name'=>'Kahve Tedarik','is_active'=>true]);
        return [$organization,$branch,$owner,$product,$supplier];
    }

    private function stockTransferFixture(): array
    {
        $organization=Organization::create(['name'=>'Stok Zinciri','code'=>'STOK'.fake()->unique()->numberBetween(100,999)]);
        $source=Branch::create(['name'=>'Kaynak','code'=>'SRC'.fake()->unique()->numberBetween(100,999)]); $target=Branch::create(['name'=>'Hedef','code'=>'DST'.fake()->unique()->numberBetween(100,999)]);
        $organization->branches()->attach([$source->id,$target->id]); $owner=User::factory()->create(['organization_id'=>$organization->id,'chain_role'=>'owner','branch_id'=>null]);
        $sourceCategory=Category::create(['branch_id'=>$source->id,'name'=>'İçecek','slug'=>'icecek-src']); $targetCategory=Category::create(['branch_id'=>$target->id,'name'=>'İçecek','slug'=>'icecek-dst']);
        $sourceProduct=Product::create(['branch_id'=>$source->id,'category_id'=>$sourceCategory->id,'name'=>'Kola','slug'=>'kola','sku'=>'KOLA-1','price'=>50,'stock_quantity'=>100,'track_stock'=>true]);
        $targetProduct=Product::create(['branch_id'=>$target->id,'category_id'=>$targetCategory->id,'name'=>'Kola','slug'=>'kola','sku'=>'KOLA-1','price'=>50,'stock_quantity'=>20,'track_stock'=>true]);
        return [$organization,$source,$target,$owner,$sourceProduct,$targetProduct];
    }
}
