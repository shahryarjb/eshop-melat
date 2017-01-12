<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_eshop pay mellat plugins
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die();
require_once JPATH_SITE . '/components/com_eshop/plugins/payment/os_trangell_inputcheck.php';

class os_mellat extends os_payment
{

	public function __construct($params) {
        $config = array(
            'type' => 0,
            'show_card_type' => false,
            'show_card_holder_name' => false
        );
        $this->setData('melatuser',$params->get('melatuser'));
        $this->setData('melatpass',$params->get('melatpass'));
        $this->setData('melatterminalId',$params->get('melatterminalId'));
        
        parent::__construct($params, $config);
	}

	public function processPayment($data) {
		$app	= JFactory::getApplication();
		$dateTime = JFactory::getDate();
			
		$fields = array(
			'terminalId' => $this->data['melatterminalId'],
			'userName' => $this->data['melatuser'],
			'userPassword' => $this->data['melatpass'],
			'orderId' => time(),
			'amount' => $data['total'],
			'localDate' => $dateTime->format('Ymd'),
			'localTime' => $dateTime->format('His'),
			'additionalData' => '',
			'callBackUrl' => JURI::root().'index.php?option=com_eshop&task=checkout.verifyPayment&payment_method=os_mellat&id='.$data['order_id'],
			'payerId' => 0,
			);
			
			try {
				$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
				$response = $soap->bpPayRequest($fields);
				
				$response = explode(',', $response->return);
				if ($response[0] != '0') { // if transaction fail
					$msg = $this->getGateMsg($response[0]); 
					$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				}
				else { // if success
					$refId = $response[1];
					echo '
						<script>
							var form = document.createElement("form");
							form.setAttribute("method", "POST");
							form.setAttribute("action", "https://bpm.shaparak.ir/pgwchannel/startpay.mellat");
							form.setAttribute("target", "_self");

							var hiddenField = document.createElement("input");
							hiddenField.setAttribute("name", "RefId");
							hiddenField.setAttribute("value", "'.$refId.'");

							form.appendChild(hiddenField);

							document.body.appendChild(form);
							form.submit();
							document.body.removeChild(form);
						</script>'
					;
				}
			}
			catch(\SoapFault $e)  {
				$msg= $this->getGateMsg('error'); 
				$app	= JFactory::getApplication();
				$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
		
	}

	protected function validate($id) {
		$app	= JFactory::getApplication();		
		$allData = EshopHelper::getOrder(intval($id)); //get all data
		//$mobile = $allData['telephone'];
		$jinput = JFactory::getApplication()->input;
		$ResCode = $jinput->post->get('ResCode', '1', 'INT'); 
		$SaleOrderId = $jinput->post->get('SaleOrderId', '1', 'INT'); 
		$SaleReferenceId = $jinput->post->get('SaleReferenceId', '1', 'INT'); 
		$RefId = $jinput->post->get('RefId', 'empty', 'STRING'); 
		if (checkHack::strip($RefId) != $RefId )
			$RefId = "illegal";
		$CardNumber = $jinput->post->get('CardHolderPan', 'empty', 'STRING'); 
		if (checkHack::strip($CardNumber) != $CardNumber )
			$CardNumber = "illegal";
		
		$this->logGatewayData(
			'OrderID:' . $id . 
			'ResCode:' . $ResCode . 
			'RefId:'.$RefId.  
			'SaleOrderId:'.$SaleOrderId.
			'SaleReferenceId:'.$SaleReferenceId.
			'CardNumber:'.$CardNumber.
			'OrderTime:'.time() 
		);
		if (
			checkHack::checkNum($id) &&
			checkHack::checkNum($ResCode) &&
			checkHack::checkNum($SaleOrderId) &&
			checkHack::checkNum($SaleReferenceId) 
		){
			if ($ResCode != '0') {
				$msg= $this->getGateMsg($ResCode); 
				$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				return false;
			}
			else {
				$fields = array(
				'terminalId' => $this->data['melatterminalId'],
				'userName' => $this->data['melatuser'],
				'userPassword' => $this->data['melatpass'],
				'orderId' => $SaleOrderId, 
				'saleOrderId' =>  $SaleOrderId, 
				'saleReferenceId' => $SaleReferenceId
				);
				try {
					$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
					$response = $soap->bpVerifyRequest($fields);

					if ($response->return != '0') {
						$msg= $this->getGateMsg($response->return); 
						$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						return false;
					}
					else {	
						$response = $soap->bpSettleRequest($fields);
						if ($response->return == '0' || $response->return == '45') {
							$this->onPaymentSuccess($id, $SaleReferenceId); 
							$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=complete',false);
							$msg= $this->getGateMsg($response->return); 
							$app->redirect($link, '<h2>'.$msg.'</h2>'.'<h3>'. $SaleReferenceId .'شماره پیگری ' .'</h3>' , $msgType='Message'); 
							return true;
						}
						else {
							$msg= $this->getGateMsg($response->return); 
							$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							return false;
						}
					}
				}
				catch(\SoapFault $e)  {
					$msg= $this->getGateMsg('error'); 
					$app	= JFactory::getApplication();
					$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					return false;
				}
			}
		}
		else {
			$msg= $this->getGateMsg('hck2'); 
			$app	= JFactory::getApplication();
			$link = JRoute::_(JUri::root().'index.php?option=com_eshop&view=checkout&layout=cancel',false);
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			return false;	
		}
	
	}

	public function verifyPayment() {
		$jinput = JFactory::getApplication()->input;
		$id = $jinput->get->get('id', '0', 'INT');
		$row = JTable::getInstance('Eshop', 'Order');
		$row->load($id);
		if ($row->order_status_id == EshopHelper::getConfigValue('complete_status_id'))
				return false;
				
		$this->validate($id);
	}

	public function getGateMsg ($msgId) {
		switch($msgId){
			case '0': $out =  'تراکنش با موفقیت انجام شد'; break;
			case '11': $out =  'شماره کارت نامعتبر است'; break;
			case '12': $out =  'موجودی کافی نیست'; break;
			case '13': $out =  'رمز نادرست است'; break;
			case '14': $out =  'تعداد دفعات وارد کردن رمز بیش از حد مجاز است'; break;
			case '15': $out =  'کارت نامعتبر است'; break;
			case '16': $out =  'دفعات برداشت وجه بیش از حد مجاز است'; break;
			case '17': $out =  'کاربر از انجام تراکنش منصرف شده است'; break;
			case '18': $out =  'تاریخ انقضای کارت گذشته است'; break;
			case '19': $out =  'مبلغ برداشت وجه بیش از حد مجاز است'; break;
			case '21': $out =  'پذیرنده نامعتبر است'; break;
			case '23': $out =  'خطای امنیتی رخ داده است'; break;
			case '24': $out =  'اطلاعات کاربری پذیرنده نادرست است'; break;
			case '25': $out =  'مبلغ نامتعبر است'; break;
			case '31': $out =  'پاسخ نامتعبر است'; break;
			case '32': $out =  'فرمت اطلاعات وارد شده صحیح نمی باشد'; break;
			case '33': $out =  'حساب نامعتبر است'; break;
			case '34': $out =  'خطای سیستمی'; break;
			case '35': $out =  'تاریخ نامعتبر است'; break;
			case '41': $out =  'شماره درخواست تکراری است'; break;
			case '42': $out =  'تراکنش Sale‌ یافت نشد'; break;
			case '43': $out =  'قبلا درخواست Verify‌ داده شده است'; break;
			case '44': $out =  'درخواست Verify‌ یافت نشد'; break;
			case '45': $out =  'تراکنش Settle‌ شده است'; break;
			case '46': $out =  'تراکنش Settle‌ نشده است'; break;
			case '47': $out =  'تراکنش  Settle یافت نشد'; break;
			case '48': $out =  'تراکنش Reverse شده است'; break;
			case '49': $out =  'تراکنش Refund یافت نشد'; break;
			case '51': $out =  'تراکنش تکراری است'; break;
			case '54': $out =  'تراکنش مرجع موجود نیست'; break;
			case '55': $out =  'تراکنش نامعتبر است'; break;
			case '61': $out =  'خطا در واریز'; break;
			case '111': $out =  'صادر کننده کارت نامعتبر است'; break;
			case '112': $out =  'خطا سوییج صادر کننده کارت'; break;
			case '113': $out =  'پاسخی از صادر کننده کارت دریافت نشد'; break;
			case '114': $out =  'دارنده کارت مجاز به انجام این تراکنش نیست'; break;
			case '412': $out =  'شناسه قبض نادرست است'; break;
			case '413': $out =  'شناسه پرداخت نادرست است'; break;
			case '414': $out =  'سازمان صادر کننده قبض نادرست است'; break;
			case '415': $out =  'زمان جلسه کاری به پایان رسیده است'; break;
			case '416': $out =  'خطا در ثبت اطلاعات'; break;
			case '417': $out =  'شناسه پرداخت کننده نامعتبر است'; break;
			case '418': $out =  'اشکال در تعریف اطلاعات مشتری'; break;
			case '419': $out =  'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است'; break;
			case '421': $out =  'IP‌ نامعتبر است';  break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
		}
		return $out;
	}
}
