<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- KDV ORANI ---
        // Yeni Nesil ÖKC mali fiş bastığı için satış kalemlerinin KDV oranı
        // cihaza bildirilmek zorundadır (gıda %10, alkollü içecek %20, su %1 ...).
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(10.00)->after('discounted_price');
        });

        // Fiş anında geçerli olan KDV oranı kalemle birlikte saklanır; oran
        // sonradan değişse bile geçmiş adisyonlar ve mali raporlar bozulmaz.
        Schema::table('check_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(10.00)->after('total_price');
        });

        // --- ÖKC ÖDEME İŞLEMLERİ ---
        // Yazdırma kuyruğuyla aynı desen: Laravel işi oluşturur, cihaz servisi
        // atomik olarak kilitler (claim), fiziki terminale iletir, sonucu bildirir.
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // sale (satış), refund (iade), void (iptal)
            $table->string('type')->default('sale');

            // Kuruş cinsinden tutar: ÖKC protokolleri tam sayı bekler, ondalık
            // yuvarlama farkı oluşmasın diye tutar burada da tam sayı tutulur.
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('TRY');
            $table->unsignedTinyInteger('installment')->default(0);

            // ÖKC'ye gönderilecek satış kalemleri + KDV kırılımı (mali fiş için)
            $table->json('payload')->nullable();

            // pending -> claimed -> sent -> awaiting_card -> approved | declined | failed | cancelled
            $table->string('status')->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // --- TERMİNAL YANITI ---
            $table->string('approval_code')->nullable();      // Provizyon / onay kodu
            $table->string('reference_number')->nullable();   // İşlem referans no (RRN)
            $table->string('masked_pan')->nullable();         // 4242****4242 (tam kart no ASLA saklanmaz)
            $table->string('card_scheme')->nullable();        // VISA, MASTERCARD, TROY
            $table->string('card_holder')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('terminal_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('fiscal_receipt_no')->nullable();  // ÖKC mali fiş / Z no
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_response')->nullable();         // Sorun giderme için ham yanıt

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'pos_tx_branch_status_index');
        });

        // --- ÖDEME KAYDINA ÖKC BAĞI ---
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('pos_transaction_id')->nullable()->after('payment_method')
                ->constrained('pos_transactions')->nullOnDelete();
            $table->string('approval_code')->nullable()->after('amount');
            $table->string('masked_pan')->nullable()->after('approval_code');
            $table->unsignedTinyInteger('installment')->default(0)->after('masked_pan');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['pos_transaction_id']);
            $table->dropColumn(['pos_transaction_id', 'approval_code', 'masked_pan', 'installment']);
        });

        Schema::dropIfExists('pos_transactions');

        Schema::table('check_items', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });
    }
};
