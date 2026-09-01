<?php

namespace App\Exceptions;

use App\Mail\SendExceptionMail;
use Http;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Throwable;

class Handler extends ExceptionHandler
{
	protected $dontReport = [
		//
	];


	protected $dontFlash = [
		'password',
		'password_confirmation',
	];

	public function report(Throwable $exception)
	{
		if ($this->shouldReport($exception) && app()->bound('sentry') && config('app.env') != 'local') {
			app('sentry')->captureException($exception);
		}

		
		// if ($this->shouldReport($exception) && config('app.env') != 'local') {
		// 	$this->sendEmail($exception);
		// }
		parent::report($exception);
	}

	public function render($request, Throwable $exception)
	{
		/**
		 * * الرسالة التقنية بتاعة اودو راحت للـ logs خلاص عن طريق report()
		 * * اللي بتشتغل قبل render() ، فهنا بنعرض للمستخدم الرسالة المفهومة
		 * * بس بدل صفحة 500
		 */
		if ($exception instanceof OdooOperationNotAllowedException) {
			if ($request->expectsJson() || $request->ajax()) {
				return response()->json([
					'status' => false,
					'msg' => $exception->getUserMessage(),
				], 422);
			}

			return back()->with('fail', $exception->getUserMessage());
		}

		return parent::render($request, $exception);
	}
	public function getAccountsToSentExceptionsFor(): array
	{
		return [
			'ahmedconan17@yahoo.com',
			// 'agaber@thetailorsdev.com',
			// 'mahmoud.youssef@squadbcc.com',
			 'asalahdev5@gmail.com'
		];
	}
	// public function sendEmail(Throwable $exception)
	// {

	// 	try {

	// 		$e = FlattenException::create($exception);
	// 		$handler = new HtmlErrorRenderer(true); // boolean, true raises debug flag...
	// 		$content = $handler->getBody($e);
	// 		foreach ($this->getAccountsToSentExceptionsFor() as $mail) {
	// 			Mail::to($mail)->send(new SendExceptionMail($content));
	// 		}
	// 	} catch (Throwable $exception) {
	// 		Log::error($exception);
	// 	}
	// }
}
