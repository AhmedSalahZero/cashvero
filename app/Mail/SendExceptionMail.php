<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendExceptionMail extends Mailable
{
	use Queueable, SerializesModels;

	/**
	 * Create a new message instance.
	 *
	 * @return void
	 */
	private $content;

	public function __construct($content)
	{
		$this->content = $content;
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build()
	{
		return $this->subject('New Exception! ( ' . config('app.name') . ' )')->from(config('app.email_from'), config('app.name'))->view('mails.exception-mail', [
			'content' => $this->content
		]);
	}
}
