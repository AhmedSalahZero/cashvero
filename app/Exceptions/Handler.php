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

		
		if ($this->shouldReport($exception) && config('app.env') != 'local') {
			$this->sendEmail($exception);
		}
		parent::report($exception);
	}

	public function render($request, Throwable $exception)
	{
		return parent::render($request, $exception);
	}
	public function getAccountsToSentExceptionsFor(): array
	{
		return [
			'ahmedconan17@yahoo.com',
			// 'agaber@thetailorsdev.com',
			'mahmoud.youssef@squadbcc.com', 'asalahdev5@gmail.com'
		];
	}
	public function sendEmail(Throwable $exception)
	{

		try {

			$e = FlattenException::create($exception);
			$handler = new HtmlErrorRenderer(true); // boolean, true raises debug flag...
			$content = $handler->getBody($e);
			foreach ($this->getAccountsToSentExceptionsFor() as $mail) {
				Mail::to($mail)->send(new SendExceptionMail($content));
			}
		} catch (Throwable $exception) {
			Log::error($exception);
		}
	}
}
