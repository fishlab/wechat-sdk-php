<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2016/3/29
 * Time: 12:30
 */

namespace Wechat\Payment;

require_once __DIR__."/lib/WxPay.Api.php";
require_once __DIR__."/lib/WxPay.Data.php";
require_once __DIR__."/lib/WxPay.Exception.php";

/**
 *
 * 扫码支付订单退款类
 * 该类实现使用于扫码支付之后原路退款
 * 1、查询交易退款情况
 * 2、如果没有退款，则发起一个退款
 *
 * 该类是微信支付提供的样例程序，商户可根据自己的需求修改，或者使用lib中的api自行开发，为了防止
 * 查询时hold住后台php进程，商户查询和撤销逻辑可在前端调用
 *
 * @author ttmk008
 *
 */

class WechatRefund extends WechatPaymentSupport
{
    /**
     *
     * 查询退款情况
     * @param string $out_trade_no  商户订单号
     * @param int $succCode         查询退款结果
     * @return
     */
    public function refundQuery($out_trade_no){
        $WxPayRefundQuery =new \WxPayRefundQuery();
        $WxPayRefundQuery->SetOut_trade_no($out_trade_no);
        $WxPayRefundQuery->setWxPayApi($this->wxPayApi);
        $result = $this->wxPayApi->refundQuery($WxPayRefundQuery);
        if($result['return_code']=='FAIL'){
            throw new \WxPayException($result['return_msg']);
        }
        if($result['return_code']=='SUCCESS'&&$result['result_code']=='SUCCESS'){
            //退款成功
            return true;
        }
        return false;
    }

    /**
     * @param $out_trade_no
     * @return bool
     * @throws \WxPayException
     */
    public function refund($out_trade_no,$fee){
        $WxPayRefund = new \WxPayRefund();
        $WxPayRefund->setWxPayApi($this->wxPayApi);
        $WxPayRefund->SetOut_trade_no($out_trade_no);
        $WxPayRefund->SetOut_refund_no($out_trade_no);
        $WxPayRefund->SetTotal_fee($fee);
        $WxPayRefund->SetRefund_fee($fee);
        $WxPayRefund->SetOp_user_id($this->wxPayApi->getWxPayConfig()['MCHID']);
        $result=$this->wxPayApi->refund($WxPayRefund);
        if($result['return_code']=='FAIL'){//系统出错
            throw new \WxPayException("接口调用失败！".$result['return_msg']);
        }
        if($result['return_code']=='SUCCESS'&&$result['result_code']=='SUCCESS'){
            //退款申请 接收成功 ，结果通过退款查询接口查询
            return true;
        }
        if($result['result_code']=='FAIL'){
            //业务提交失败
            throw new \WxPayException("业务提交失败！");
        }
        return false;
    }
}
