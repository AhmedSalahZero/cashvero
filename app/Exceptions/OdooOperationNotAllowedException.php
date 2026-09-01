<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * * اودو بيرفض عمليات كتير لاسباب محاسبية (اشهرها ان القيد استهلك رقم
 * * تسلسلي و مش آخر واحد في السلسلة) و بيرجّع رسالة انجليزية طويلة
 * * ما تنفعش تتعرض للمستخدم
 *
 * * الاستثناء ده بيفصل الاتنين : الرسالة التقنية بتروح للـ logs زي ما هي
 * * (عن طريق report) ، و المستخدم بياخد رسالة مفهومة
 *
 * * و بيتـرمي بس بعد ما تكون حالة اودو رجعت زي ما كانت — راجع
 * * HasAtomicOdooDeletion
 */
class OdooOperationNotAllowedException extends Exception
{
    private string $userMessage;

    public function __construct(string $odooMessage, ?string $userMessage = null, ?Throwable $previous = null)
    {
        $this->userMessage = $userMessage ?: __('Your account does not have the permission to perform this change');

        parent::__construct($odooMessage, 0, $previous);
    }

    /**
     * * الرسالة اللي تتعرض للمستخدم
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
