<?php

namespace App\Traits\Models;

use App\Models\PaymentSettlement;
use App\Models\Settlement;
use App\Models\SupplierInvoice;

/**
 * * فاتورة نازل عليها تسويات ما ينفعش تتمسح
 *
 * * التسوية بتقول "المبلغ ده اتسدد من الفاتورة الفلانية" ، فلما الفاتورة
 * * تتمسح التسوية بتفضل معلقة في الهوا : الفلوس محسوبة على رصيد الشريك
 * * لكن مفيش حاجة تقول مقابل ايه — و ده اللي كان بيخلي بوب اب تفاصيل
 * * التسوية يعرض صف بمبلغ حقيقي و فاتورة مش موجودة
 *
 * * مكانش فيه اي حاجة تمنع ده : لا هوك على الموديل و لا foreign key على
 * * settlements.invoice_id في اي من النظامين ، فالمسح كان بيعدي و يسيب
 * * التسويات يتيمة
 *
 * * الحارس هنا على مستوى الموديل عشان يغطي كل المسارات مرة واحدة :
 * * المسح الجماعي من الشاشة ، و مزامنة الفواتير المحذوفة من اودو ، و اي
 * * كود جديد يتكتب بعد كده
 */
trait CannotBeDeletedWhileSettled
{
    public static function bootCannotBeDeletedWhileSettled(): void
    {
        static::deleting(function ($invoice): void {
            $count = $invoice->settlementsCountForDeletionGuard();

            if ($count > 0) {
                throw new \InvalidArgumentException(__(
                    'Invoice :number cannot be deleted because :count settlement(s) are recorded against it.',
                    [
                        'number' => $invoice->getInvoiceNumber() ?: $invoice->getKey(),
                        'count' => $count,
                    ]
                ));
            }
        });
    }

    /**
     * * فواتير العملاء بتتسوّى في settlements و فواتير الموردين في
     * * payment_settlements
     */
    public function settlementsCountForDeletionGuard(): int
    {
        $settlementClass = $this instanceof SupplierInvoice ? PaymentSettlement::class : Settlement::class;

        return $settlementClass::where('invoice_id', $this->getKey())->count();
    }
}
