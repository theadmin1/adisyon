<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function create(User $user, int $sourceBranchId, int $targetBranchId, array $items, ?string $notes): StockTransfer
    {
        $this->assertBranches($user, $sourceBranchId, $targetBranchId);
        return DB::transaction(function () use ($user, $sourceBranchId, $targetBranchId, $items, $notes): StockTransfer {
            $sourceIds = collect($items)->pluck('product_id')->map(fn ($id) => (int)$id)->all();
            $sources = Product::forBranch($sourceBranchId)->whereIn('id', $sourceIds)->where('track_stock', true)->get()->keyBy('id');
            if ($sources->count() !== count(array_unique($sourceIds))) throw ValidationException::withMessages(['items'=>'Kaynak şubede geçersiz veya stok takibi kapalı ürün var.']);
            $targets = Product::forBranch($targetBranchId)->whereIn('sku', $sources->pluck('sku'))->get()->keyBy('sku');
            $transfer = StockTransfer::create(['organization_id'=>$user->organization_id,'source_branch_id'=>$sourceBranchId,'target_branch_id'=>$targetBranchId,'created_by_user_id'=>$user->id,'transfer_number'=>'TR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),'status'=>'requested','notes'=>$notes]);
            foreach ($items as $input) {
                $source = $sources->get((int)$input['product_id']); $quantity=round((float)$input['quantity'],3); $target=$targets->get($source->sku);
                if ($quantity<=0) throw ValidationException::withMessages(['items'=>'Transfer miktarı sıfırdan büyük olmalıdır.']);
                if (!$source->sku || !$target) throw ValidationException::withMessages(['items'=>"{$source->name} hedef şubede aynı SKU ile bulunamadı."]);
                $transfer->items()->create(['source_product_id'=>$source->id,'target_product_id'=>$target->id,'product_name'=>$source->name,'sku'=>$source->sku,'quantity'=>$quantity,'unit'=>$source->unit ?: 'adet']);
            }
            return $transfer->load('items');
        });
    }

    public function approve(StockTransfer $transfer, User $user): void
    {
        $this->assertTransfer($transfer,$user);
        DB::transaction(function () use ($transfer,$user): void {
            $locked=StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if($locked->status!=='requested') throw ValidationException::withMessages(['transfer'=>'Yalnızca bekleyen transfer onaylanabilir.']);
            foreach($locked->items()->get() as $item){$product=Product::forBranch($locked->source_branch_id)->whereKey($item->source_product_id)->lockForUpdate()->firstOrFail(); if((float)$product->stock_quantity<(float)$item->quantity) throw ValidationException::withMessages(['stock'=>"{$product->name} için yeterli stok yok."]); $product->decrement('stock_quantity',$item->quantity); $this->movement($product,'transfer_out',$item->quantity,$user,"Transfer çıkışı {$locked->transfer_number}");}
            $locked->update(['status'=>'approved','approved_by_user_id'=>$user->id,'approved_at'=>now()]);
        });
    }

    public function ship(StockTransfer $transfer, User $user): void
    {
        $this->assertTransfer($transfer,$user); $updated=StockTransfer::whereKey($transfer->id)->where('status','approved')->update(['status'=>'shipped','shipped_at'=>now()]);
        if(!$updated) throw ValidationException::withMessages(['transfer'=>'Yalnızca onaylanmış transfer sevk edilebilir.']);
    }

    public function receive(StockTransfer $transfer, User $user): void
    {
        $this->assertTransfer($transfer,$user);
        DB::transaction(function () use ($transfer,$user): void {$locked=StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail(); if($locked->status!=='shipped') throw ValidationException::withMessages(['transfer'=>'Yalnızca sevk edilmiş transfer teslim alınabilir.']); foreach($locked->items()->get() as $item){$product=Product::forBranch($locked->target_branch_id)->whereKey($item->target_product_id)->lockForUpdate()->firstOrFail(); $product->increment('stock_quantity',$item->quantity); $this->movement($product,'transfer_in',$item->quantity,$user,"Transfer girişi {$locked->transfer_number}");} $locked->update(['status'=>'received','received_by_user_id'=>$user->id,'received_at'=>now()]);});
    }

    public function cancel(StockTransfer $transfer, User $user): void
    {
        $this->assertTransfer($transfer,$user);
        DB::transaction(function () use ($transfer,$user): void {$locked=StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail(); if(!in_array($locked->status,['requested','approved'],true)) throw ValidationException::withMessages(['transfer'=>'Sevk edilmiş veya tamamlanmış transfer iptal edilemez.']); if($locked->status==='approved'){foreach($locked->items()->get() as $item){$product=Product::forBranch($locked->source_branch_id)->whereKey($item->source_product_id)->lockForUpdate()->firstOrFail(); $product->increment('stock_quantity',$item->quantity); $this->movement($product,'transfer_cancel_return',$item->quantity,$user,"Transfer iptal iadesi {$locked->transfer_number}");}} $locked->update(['status'=>'cancelled','cancelled_at'=>now()]);});
    }

    private function assertBranches(User $user,int $source,int $target): void { if($source===$target) throw ValidationException::withMessages(['target_branch_id'=>'Kaynak ve hedef şube farklı olmalıdır.']); $ids=$user->accessibleChainBranchIds(); if(!in_array($source,$ids,true)||!in_array($target,$ids,true)) abort(403); }
    private function assertTransfer(StockTransfer $transfer,User $user): void { abort_unless($transfer->organization_id===$user->organization_id,404); $this->assertBranches($user,$transfer->source_branch_id,$transfer->target_branch_id); }
    private function movement(Product $product,string $type,float $quantity,User $user,string $notes): void { StockMovement::create(['branch_id'=>$product->branch_id,'product_id'=>$product->id,'sync_uuid'=>(string)Str::uuid(),'is_synced'=>true,'type'=>$type,'quantity'=>$quantity,'status'=>'completed','approved_by_user_id'=>$user->id,'approved_at'=>now(),'notes'=>$notes]); }
}
