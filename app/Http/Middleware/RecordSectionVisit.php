<?php

namespace App\Http\Middleware;

use App\Models\Log;
use App\Support\SectionLabel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بيسجل في جدول logs كل صفحة المستخدم بيفتحها جوه شركة (المشروع ده
 * كله CashVero). بيشتغل بعد ما الصفحة تترد. التنقل بـ Inertia بيتحسب
 * زيارة، لكن الـ partial reload والـ ajax والـ JSON وتحميل الملفات وأي
 * رد مش ناجح ما بيتحسبوش. أي خطأ في التسجيل ما يوقفش الصفحة.
 */
class RecordSectionVisit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if ($this->shouldRecord($request, $response)) {
                Log::storeNewLogRecord(
                    'enterSection',
                    $request->user(),
                    SectionLabel::forRoute($request->route()),
                    (int) $request->segment(2)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function shouldRecord(Request $request, $response): bool
    {
        if (! $request->isMethod('GET') || ! $request->user()) {
            return false;
        }

        $route = $request->route();
        if (! $route instanceof Route || ! $route->getName()) {
            return false;
        }

        if (! is_numeric($request->segment(2))) {
            return false;
        }

        return $this->isPageResponse($request, $response);
    }

    private function isPageResponse(Request $request, $response): bool
    {
        if (! $response instanceof Response || ! $response->isSuccessful()) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($request->hasHeader('X-Inertia')) {
            return ! $request->hasHeader('X-Inertia-Partial-Data');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        return str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html');
    }
}
