<?php
namespace Wechat\Payment;

require_once __DIR__."/lib/WxPay.Api.php";
require_once __DIR__."/lib/WxPay.Data.php";
require_once __DIR__."/lib/WxPay.Exception.php";

/**
 * 
 * 刷卡支付实现类
 * 该类实现了一个刷卡支付的流程，流程如下：
 * 1、提交刷卡支付
 * 2、根据返回结果决定是否需要查询订单，如果查询之后订单还未变则需要返回查询（一般反复查10次）
 * 3、如果反复查询10订单依然不变，则发起撤销订单
 * 4、撤销订单需要循环撤销，一直撤销成功为止（注意循环次数，建议10次）
 * 
 * 该类是微信支付提供的样例程序，商户可根据自己的需求修改，或者使用lib中的api自行开发，为了防止
 * 查询时hold住后台php进程，商户查询和撤销逻辑可在前端调用
 * 
 * @author widy
 *
 */
class WechatMicroPayment extends WechatPaymentSupport
{
	/**
	 * 
	 * 提交刷卡支付，并且确认结果，接口比较慢
	 * @param WxPayMicroPay $microPayInput
	 * @throws WxpayException
	 * @return 返回查询接口的结果
	 */
	public function pay($microPayInput,$maxQueryTimes=6,$queryInterval=2)
	{
		//①、提交被扫支付
		$microPayInput ->setWxPayApi($this->wxPayApi);
		$result = $this->wxPayApi->micropay($microPayInput, $this->wxPayConfig['CURL_TIMEOUT']);

		//如果返回成功
/*		if(!array_key_exists("return_code", $result)
			|| !array_key_exists("out_trade_no", $result)
			|| !array_key_exists("result_code", $result))*/
		if(!array_key_exists("return_code", $result)
			|| !array_key_exists("result_code", $result))
		{
			throw new \WxPayException("interface_get_failure");
		}

		//签名验证
		$out_trade_no = $microPayInput->GetOut_trade_no();

		//②、接口调用成功，明确返回调用失败
		if($result["return_code"] == "SUCCESS" &&
		   $result["result_code"] == "FAIL" && 
		   $result["err_code"] != "USERPAYING" && 
		   $result["err_code"] != "SYSTEMERROR")
		{
			throw new \WxPayException($result['err_code']);
//			return false;
		}

		//③、确认支付是否成功
		$queryTimes = $maxQueryTimes;
		while($queryTimes > 0)
		{
			$succResult = 0;
			$queryResult = $this->query($out_trade_no, $succResult);
			//如果需要等待1s后继续
			if($succResult == 2){
				sleep($queryInterval);
				$queryTimes--;
				continue;
			} else if($succResult == 1){//查询成功
				return $queryResult;
			} else {//订单交易失败
				return false;
			}

		}
		
		//④、次确认失败，则撤销订单
		if(!$this->cancel($out_trade_no))
		{
			throw new \WxpayException("cancel_order_failure");
		}
		throw new \Exception("wait_pay_time_out");

//		return false;
	}
	
	/**
	 * 
	 * 查询订单情况
	 * @param string $out_trade_no  商户订单号
	 * @param int $succCode         查询订单结果
	 * @return 0 订单不成功，1表示订单成功，2表示继续等待
	 */
	public function query($out_trade_no, &$succCode)
	{
		$queryOrderInput = new \WxPayOrderQuery();
		$queryOrderInput ->setWxPayApi($this->wxPayApi);
        \Log::info( 'curl timeout :' . $this->wxPayConfig['CURL_TIMEOUT']);
		$queryOrderInput->SetOut_trade_no($out_trade_no);
		$result = $this->wxPayApi->orderQuery($queryOrderInput, $this->wxPayConfig['CURL_TIMEOUT']);
		
		if($result["return_code"] == "SUCCESS" 
			&& $result["result_code"] == "SUCCESS")
		{
			//支付成功
			if($result["trade_state"] == "SUCCESS"){
				$succCode = 1;
			   	return $result;
			}
			//用户支付中
			else if($result["trade_state"] == "USERPAYING"){
				$succCode = 2;
				return false;
			}
		}
		
		//如果返回错误码为“此交易订单号不存在”则直接认定失败
		if(isset($result["err_code"]) && $result["err_code"] == "ORDERNOTEXIST")
		{
			$succCode = 0;
		} else{
			//如果是系统错误，则后续继续
			$succCode = 2;
		}
		return false;
	}
	
	/**
	 * 
	 * 撤销订单，如果失败会重复调用10次
	 * @param string $out_trade_no
	 * @param 调用深度 $depth
	 */
	public function cancel($out_trade_no, $depth = 0)
	{
		if($depth > 10){
			return false;
		}
		
		$clostOrder = new \WxPayReverse();
		$clostOrder->setWxPayApi($this->wxPayApi);
		$clostOrder->SetOut_trade_no($out_trade_no);
		$result = $this->wxPayApi->reverse($clostOrder,$this->wxPayConfig['CURL_TIMEOUT']);
		//接口调用失败
		if (!isset($result["return_code"]) ){
		    return false;
        }
        if($result["return_code"] != "SUCCESS"){
			return false;
		}
		
		//如果结果为success且不需要重新调用撤销，则表示撤销成功
		if($result["result_code"] != "SUCCESS" 
			&& $result["recall"] == "N"){
			return true;
		} else if($result["recall"] == "Y") {
			return $this->cancel($out_trade_no, ++$depth);
		}
		return false;
	}

	public function setTradeType($trade_type)
	{
		throw new \Exception();
	}

	public function setNotifyUrl($notify_url)
	{
		throw new \Exception();
	}
}