<?php
namespace App\Traits;


trait HasOdooPaymentMethod
{
	public function getOdooInboundTransferPaymentMethodId()
	{
		return $this->odoo_inbound_transfer_payment_method_id;
	}
	public function getOdooOutboundTransferPaymentMethodId()
	{
		return $this->odoo_outbound_transfer_payment_method_id;
	}
	public function getOdooInboundChequePaymentMethodId()
	{
		return $this->odoo_inbound_cheque_payment_method_id;
	}
	public function getOdooOutboundChequePaymentMethodId()
	{
		return $this->odoo_outbound_cheque_payment_method_id;
	}
}
