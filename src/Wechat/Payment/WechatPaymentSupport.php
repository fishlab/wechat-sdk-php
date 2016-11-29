<?php
/**
 * Created by PhpStorm.
 * User: wu
 * Date: 2016/3/3
 * Time: 14:39
 */

namespace Wechat\Payment;

require_once __DIR__ . '/lib/WxPay.Api.php';
require_once __DIR__ . '/lib/WxPay.Data.php';

abstract class WechatPaymentSupport
{

    protected $notify_url;

    protected $trade_type;

    protected $wxPayConfig;

    protected $wxPayApi;

    static function loadWechatPaymentData(){
        return require_once( __DIR__.'/lib/WxPay.Data.php' );
    }

    public function __construct(array $wxPayConfig)
    {
        $defaultConfig = require(__DIR__.'/config/wechat.payment.default.config.php');
        $this->wxPayConfig = array_merge($defaultConfig,$wxPayConfig);
        $this->wxPayApi = new \WxPayApi( $this->wxPayConfig );
    }

    /**
     * @return mixed
     */
    public function getNotifyUrl()
    {
        return $this->notify_url;
    }

    /**
     * @param mixed $notify_url
     */
    public function setNotifyUrl($notify_url)
    {
        $this->notify_url = $notify_url;
    }

    /**
     * @return mixed
     */
    public function getTradeType()
    {
        return $this->trade_type;
    }

    /**
     * @param mixed $trade_type
     */
    public function setTradeType($trade_type)
    {
        $this->trade_type = $trade_type;
    }

    public function createUnifiedOrder($out_trade_no, $subject, $total_fee, $open_id = null)
    {
        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        $input->setWxPayApi($this->wxPayApi);

        $input->SetBody($subject);
        $input->SetOut_trade_no($out_trade_no);
//        $input->SetTotal_fee($total_fee);
        $input->SetTotal_fee(intval($total_fee * 100));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetTrade_type($this->trade_type);
        if ($open_id) {
            $input->SetOpenid($open_id);
        }
        $order = $this->wxPayApi->unifiedOrder($input);
        return $order;
    }

    // query order
    public function queryOrder($out_trade_no)
    {
        $input = new \WxPayOrderQuery();
        $input->setWxPayApi($this->wxPayApi);
        $input->SetOut_trade_no($out_trade_no);
        $result = \WxPayApi::orderQuery($input);
        /*
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return true;
        }
        */
        return $result;
    }

}