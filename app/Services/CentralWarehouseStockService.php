<?php

namespace App\Services;

use App\Models\ChainInventoryMovement;
use App\Models\ChainMenuProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CentralWarehouseStockService
{
    public function __construct(private readonly ChainMenuPublisher $publisher) {}

    public function adjust(User $user,array $input): array
    {
        $validated=Validator::make($input,[
            'product_id'=>['required','integer'],'operation'=>['required','in:add,subtract,set'],
            'quantity'=>['required','numeric','min:0'],'min_stock_level'=>['nullable','numeric','min:0'],
            'notes'=>['nullable','string','max:1000'],
        ])->validate();
        return DB::transaction(function()use($user,$validated):array{
            $product=$this->lockProduct($user,(int)$validated['product_id']);
            $old=(float)$product->stock_quantity; $quantity=(float)$validated['quantity'];
            if($validated['operation']!=='set'&&$quantity<=0) throw ValidationException::withMessages(['quantity'=>'Giriş veya çıkış miktarı sıfırdan büyük olmalıdır.']);
            $new=match($validated['operation']){'add'=>$old+$quantity,'subtract'=>$old-$quantity,'set'=>$quantity};
            if($new<0) throw ValidationException::withMessages(['quantity'=>'Merkez depo stoğu eksiye düşürülemez.']);
            $changes=['stock_quantity'=>$new];
            if(array_key_exists('min_stock_level',$validated)&&$validated['min_stock_level']!==null) $changes['min_stock_level']=(float)$validated['min_stock_level'];
            $product->update($changes); $difference=$new-$old;
            if(abs($difference)>0.00001) ChainInventoryMovement::create([
                'organization_id'=>$user->organization_id,'chain_menu_product_id'=>$product->id,
                'type'=>$validated['operation']==='set'?'stock_count':($difference>0?'central_addition':'central_subtraction'),
                'quantity'=>abs($difference),'unit'=>$product->unit,'stock_before'=>$old,'stock_after'=>$new,
                'created_by_user_id'=>$user->id,'notes'=>$validated['notes']??null,
            ]);
            return ['product'=>$product->name,'quantity'=>$new,'unit'=>$product->unit];
        });
    }

    public function distribute(User $user,array $input): array
    {
        $input['allocations']=array_values(array_filter($input['allocations']??[],fn($row)=>(float)($row['quantity']??0)>0));
        $validated=Validator::make($input,[
            'product_id'=>['required','integer'],'allocations'=>['required','array','min:1'],
            'allocations.*.branch_id'=>['required','integer','distinct'],'allocations.*.quantity'=>['required','numeric','gt:0'],
            'notes'=>['nullable','string','max:1000'],
        ])->validate();
        $branchIds=array_map(fn($row)=>(int)$row['branch_id'],$validated['allocations']);
        abort_unless(count(array_intersect($branchIds,$user->accessibleChainBranchIds()))===count($branchIds),403);

        return DB::transaction(function()use($user,$validated):array{
            $product=$this->lockProduct($user,(int)$validated['product_id'])->load(['organization','category']);
            $total=array_sum(array_map(fn($row)=>(float)$row['quantity'],$validated['allocations']));
            if($total>(float)$product->stock_quantity+0.00001) throw ValidationException::withMessages(['allocations'=>'Dağıtım toplamı merkez depo stoğunu aşamaz.']);
            $remaining=(float)$product->stock_quantity;
            foreach($validated['allocations'] as $allocation){
                $branchId=(int)$allocation['branch_id']; $quantity=(float)$allocation['quantity'];
                $this->publisher->publish($product,[$branchId]);
                $branchProduct=Product::withoutGlobalScope('authenticated_branch')->where('branch_id',$branchId)->where('sku',$product->sku)->lockForUpdate()->firstOrFail();
                $branchBefore=(float)$branchProduct->stock_quantity; $branchAfter=$branchBefore+$quantity;
                $branchProduct->update(['stock_quantity'=>$branchAfter,'unit'=>$product->unit,'track_stock'=>true,'is_synced'=>config('database.default')==='mysql']);
                StockMovement::create([
                    'product_id'=>$branchProduct->id,'sync_uuid'=>(string)Str::uuid(),'is_synced'=>config('database.default')==='mysql',
                    'type'=>'central_distribution','quantity'=>$quantity,'status'=>'completed','approved_by_user_id'=>$user->id,'approved_at'=>now(),
                    'notes'=>'Merkez F&B deposundan dağıtım'.(filled($validated['notes']??null)?': '.$validated['notes']:''),
                ]);
                $before=$remaining; $remaining-=$quantity;
                ChainInventoryMovement::create([
                    'organization_id'=>$user->organization_id,'chain_menu_product_id'=>$product->id,'branch_id'=>$branchId,
                    'type'=>'distribution_out','quantity'=>$quantity,'unit'=>$product->unit,'stock_before'=>$before,'stock_after'=>$remaining,
                    'created_by_user_id'=>$user->id,'notes'=>$validated['notes']??null,
                ]);
            }
            $product->update(['stock_quantity'=>$remaining]);
            return ['product'=>$product->name,'distributed'=>$total,'remaining'=>$remaining,'unit'=>$product->unit,'branches'=>count($validated['allocations'])];
    private function lockProduct(User $user,int $productId): ChainMenuProduct
    {
        return ChainMenuProduct::query()->whereKey($productId)->where('organization_id',$user->organization_id)
            ->where('item_type','raw_material')->where('track_stock',true)->lockForUpdate()->firstOrFail();
    }
}
