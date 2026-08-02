<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChainStockAdjustmentService
{
    public function adjust(User $user, array $input): array
    {
        $validated=Validator::make($input,[
            'branch_id'=>['required','integer'],
            'product_id'=>['required','integer'],
            'operation'=>['required','in:add,subtract,set'],
            'quantity'=>['required','numeric','min:0'],
            'min_stock_level'=>['nullable','numeric','min:0'],
            'notes'=>['nullable','string','max:1000'],
        ])->validate();
        $branchId=(int)$validated['branch_id'];
        abort_unless(in_array($branchId,$user->accessibleChainBranchIds(),true),403);

        return DB::transaction(function()use($validated,$branchId,$user):array{
            $product=Product::withoutGlobalScope('authenticated_branch')->whereKey((int)$validated['product_id'])
                ->where('branch_id',$branchId)->where('track_stock',true)->lockForUpdate()->firstOrFail();
            $old=(float)$product->stock_quantity;
            $quantity=(float)$validated['quantity'];
            if($validated['operation']!=='set'&&$quantity<=0){
                throw ValidationException::withMessages(['quantity'=>'Giriş veya çıkış miktarı sıfırdan büyük olmalıdır.']);
            }
            $new=match($validated['operation']){'add'=>$old+$quantity,'subtract'=>$old-$quantity,'set'=>$quantity};
            if($new<0){
                throw ValidationException::withMessages(['quantity'=>'Çıkış miktarı mevcut '.rtrim(rtrim(number_format($old,3,'.',''),'0'),'.').' '.$product->unit.' stoktan fazla olamaz.']);
            }
            $changes=['stock_quantity'=>$new,'is_synced'=>config('database.default')==='mysql'];
            if(array_key_exists('min_stock_level',$validated)&&$validated['min_stock_level']!==null){
                $changes['min_stock_level']=(float)$validated['min_stock_level'];
            }
            $product->update($changes);
            $difference=$new-$old;
            if(abs($difference)>0.00001){
                StockMovement::create([
                    'product_id'=>$product->id,'sync_uuid'=>(string)Str::uuid(),
                    'is_synced'=>config('database.default')==='mysql','type'=>$difference>0?'manual_addition':'manual_subtraction',
                    'quantity'=>abs($difference),'status'=>'completed','approved_by_user_id'=>$user->id,'approved_at'=>now(),
                    'notes'=>'Zincir yönetimi stok işlemi'.(filled($validated['notes']??null)?': '.$validated['notes']:''),
                ]);
            }
            return ['product'=>$product->name,'quantity'=>$new,'unit'=>$product->unit];
        });
    }
}
