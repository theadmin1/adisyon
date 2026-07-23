<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;

/**
 * Ayarlar > Yazıcı Durumu sekmesi: merkezi yazdırma kuyruğunun yönetimi.
 *
 * Yazıcı TANIMLARI burada yapılmaz. Hangi Windows yazıcısının kurulu olduğunu
 * yalnızca cihazın kendisi bilebildiği için eşleştirme, kasadaki servis
 * programının admin panelindeki "Termal Yazıcılar" ekranından yapılır; cihaz
 * kağıt/satır genişliğini POST /api/v1/print/printers ile buraya bildirir.
 */
class PrinterSettingController extends Controller
{
    /**
     * Başarısız olmuş bir yazdırma işini kuyruğa geri koyar.
     */
    public function requeue(PrintJob $job): JsonResponse
    {
        $job->update([
            'status' => PrintJob::STATUS_PENDING,
            'claimed_at' => null,
            'attempts' => 0,
            'error_message' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "#{$job->id} numaralı fiş yeniden kuyruğa alındı.",
            'job_id' => $job->id,
        ]);
    }
}
