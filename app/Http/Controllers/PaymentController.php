<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
/** All Paypal Details class **/
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;
use URL;
use Illuminate\Support\Facades\Input;

class PaymentController extends Controller
{
	// Chứa context API
	private $_api_context;

    public function __construct()
    {
    	/** PayPal api context (Đọc các cài đặt trong file config) **/
        $paypal_conf = \Config::get('paypal');

        // Khởi tạo ngữ cảnh
        $this->_api_context = new ApiContext(new OAuthTokenCredential(
            $paypal_conf['client_id'],
            $paypal_conf['secret'])
        );
        $this->_api_context->setConfig($paypal_conf['settings']);
    }

    public function payWithpaypal(Request $request)
    {
    	// Chọn kiểu thanh toán.
    	$payer = new Payer();
        $payer->setPaymentMethod('paypal');

        // Khởi tạo item
        $item_1 = new Item();

        $item_1->setName('Item 1') /** item name **/
            ->setCurrency('USD')
            ->setQuantity(1)
            ->setPrice($request->get('amount')); /** unit price **/

        // Danh sách các item
        $item_list = new ItemList();
        $item_list->setItems(array($item_1));

        // Tổng tiền và kiểu tiền sẽ sử dụng để thanh toán.
        // Nên đồng nhất kiểu tiền của item và kiểu tiền của đơn hàng
        // tránh trường hợp đơn vị tiền của item là JPY nhưng của đơn hàng
        // lại là USD.
        $amount = new Amount();
        $amount->setCurrency('USD')
            ->setTotal($request->get('amount'));

        // Transaction
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($item_list)
            ->setDescription('Payment description');

        // Đường dẫn để xử lý một thanh toán thành công.
        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(URL::route('status')) /** Specify return URL **/
            ->setCancelUrl(URL::route('status'));

        // Khởi tạo một payment
        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));

        /** dd($payment->create($this->_api_context));exit; **/
        // Thực hiện việc tạo payment
        try {
        	$payment->create($this->_api_context);
        } catch (\PayPal\Exception\PPConnectionException $ex) {
    		if (\Config::get('app.debug')) {
    			\Session::put('error', 'Connection timeout');
            return Redirect::route('paywithpaypal');
        	} else {
        		\Session::put('error', 'Some error occur, sorry for inconvenient');
            	return Redirect::route('paywithpaypal');
            }
        }
        dd('123');
        // Nếu việc thanh tạo một payment thành công. Chúng ta sẽ nhận
    	// được một danh sách các đường dẫn liên quan đến việc
    	// thanh toán trên PayPal
        foreach ($payment->getLinks() as $link) {
        	// Duyệt từng link và lấy link nào có rel
            // là approval_url rồi gán nó vào $redirect_url
            // để chuyển hướng người dùng đến đó.
        	if ($link->getRel() == 'approval_url') {
        		$redirect_url = $link->getHref();
            	break;
        	}
        }
        /** add payment ID to session **/
        // Lưu payment ID vào session để kiểm tra
        Session::put('paypal_payment_id', $payment->getId());

        if (isset($redirect_url)) {
        	/** redirect to paypal **/
            return Redirect::away($redirect_url);
        }

        \Session::put('error', 'Unknown error occurred');
        return Redirect::route('paywithpaypal');
    }

    public function getPaymentStatus()
    {
    	/** Get the payment ID before session clear **/
    	// Lấy Payment ID từ session
        $payment_id = Session::get('paypal_payment_id');

        /** clear the session payment ID **/
        // Xóa payment ID đã lưu trong session
        Session::forget('paypal_payment_id');

        // Kiểm tra xem URL trả về từ PayPal có chứa
        // các query cần thiết của một thanh toán thành công
        // hay không.
        if (empty(Input::get('PayerID')) || empty(Input::get('token'))) {

            \Session::put('error', 'Payment failed');
            return Redirect::to('/');

        }

        // Khởi tạo payment từ Payment ID đã có
        $payment = Payment::get($payment_id, $this->_api_context);

        // Thực thi payment và lấy payment detail
        $execution = new PaymentExecution();
        $execution->setPayerId(Input::get('PayerID'));

        /**Execute the payment **/
        $result = $payment->execute($execution, $this->_api_context);

        if ($result->getState() == 'approved') {
            \Session::put('success', 'Payment success');
            return Redirect::to('/');
        }

        \Session::put('error', 'Payment failed');
        return Redirect::to('/');
    }
}
