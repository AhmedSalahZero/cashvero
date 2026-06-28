<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DueInvoiceNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
	protected $message_en , $message_ar ,$type,$tapType , $dataArray ; 
	/**
	 * * tapType => هي عباره عن الرو دا هيتحط في انهي تابه ؟ هل تابه ال كاستمرز ولا ولا
	 */
    public function __construct($messageEn , $messageAr,$type,$tapType,array $dataArray)
    {
   
		$this->message_en = $messageEn;
		$this->message_ar = $messageAr;
		$this->type = $type;
		$this->tapType = $tapType;
		$this->dataArray = $dataArray ;
    }


    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
			'type'=>$this->type,
			'message_en'=>$this->message_en,
			'message_ar'=>$this->message_ar,
			'tap_type'=>$this->tapType,
			'data_array'=>$this->dataArray
        ];
    }
}
