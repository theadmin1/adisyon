<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChainInventoryMovement;
use App\Models\ChainMenuProduct;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Services\ChainStockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ChainStockController extends Controller
{
    public function index(Request $request): View
    {
        $branchIds=Auth::user()->accessibleChainBranchIds(); $branches=Branch::whereIn('id',$branchIds)->orderBy('name')->get();
        $products=Product::withoutGlobalScope('authenticated_branch')->with('branch')->whereIn('branch_id',$branchIds)->where('track_stock',true)->whereNotNull('sku')->orderBy('name')->get();
        $stockRows=$products->groupBy('sku')->map(function($group) use($branches){$first=$group->first(); return (object)['sku'=>$first->sku,'name'=>$first->name,'unit'=>$first->unit,'branches'=>$branches->mapWithKeys(fn($branch)=>[$branch->id=>$group->firstWhere('branch_id',$branch->id)])];})->values();
        $transfers=StockTransfer::with(['sourceBranch','targetBranch','items','createdBy'])->where('organization_id',Auth::user()->organization_id)->where(function($q)use($branchIds){$q->whereIn('source_branch_id',$branchIds)->orWhereIn('target_branch_id',$branchIds);})->latest()->paginate(20)->withQueryString();
        $centralProducts=ChainMenuProduct::where('organization_id',Auth::user()->organization_id)->where('item_type','raw_material')->where('track_stock',true)->orderBy('name')->get();
        $centralMovements=Schema::hasTable('chain_inventory_movements')
            ? ChainInventoryMovement::with(['product','branch'])->where('organization_id',Auth::user()->organization_id)->latest()->limit(30)->get()
            : collect();
        $canTransfer=Auth::user()->chain_role!=='analyst';
        return view('chain.stocks.index',compact('branches','products','stockRows','transfers','centralProducts','centralMovements','canTransfer'));
    }

    public function store(Request $request,StockTransferService $service): RedirectResponse
    {
        $this->authorizeMutation(); $validated=$request->validate(['source_branch_id'=>['required','integer'],'target_branch_id'=>['required','integer','different:source_branch_id'],'notes'=>['nullable','string','max:1000'],'items'=>['required','array','min:1'],'items.*.product_id'=>['required','integer','distinct'],'items.*.quantity'=>['required','numeric','gt:0']]);
        $transfer=$service->create(Auth::user(),(int)$validated['source_branch_id'],(int)$validated['target_branch_id'],$validated['items'],$validated['notes']??null);
        return back()->with('success',"{$transfer->transfer_number} numaralı transfer talebi oluşturuldu.");
    }

    public function adjust(Request $request,ChainStockAdjustmentService $service): RedirectResponse { $this->authorizeMutation(); $result=$service->adjust(Auth::user(),$request->all()); return back()->with('success',$result['product'].' stoğu '.rtrim(rtrim(number_format($result['quantity'],3,'.',''),'0'),'.').' '.$result['unit'].' olarak güncellendi.'); }
    public function approve(StockTransfer $transfer,StockTransferService $service): RedirectResponse { $this->authorizeMutation(); $service->approve($transfer,Auth::user()); return back()->with('success','Transfer onaylandı ve kaynak stok rezerve edildi.'); }
    public function ship(StockTransfer $transfer,StockTransferService $service): RedirectResponse { $this->authorizeMutation(); $service->ship($transfer,Auth::user()); return back()->with('success','Transfer sevk edildi.'); }
    public function receive(StockTransfer $transfer,StockTransferService $service): RedirectResponse { $this->authorizeMutation(); $service->receive($transfer,Auth::user()); return back()->with('success','Transfer teslim alındı ve hedef stoğa işlendi.'); }
    public function cancel(StockTransfer $transfer,StockTransferService $service): RedirectResponse { $this->authorizeMutation(); $service->cancel($transfer,Auth::user()); return back()->with('success','Transfer iptal edildi.'); }
    private function authorizeMutation(): void { abort_if(Auth::user()->chain_role==='analyst',403); }
}
