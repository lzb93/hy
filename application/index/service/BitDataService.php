<?php
namespace app\index\service;

// 比特币数据获取服务类
use think\Log;

class BitDataService {
    private static $user = 'yifeng1';
    private static $passwd = 'yf20180323$%$1^.';

    public static function curlfun($url, $params = array(), $method = 'GET', $gzip=false) {
        $header = array();
        $opts = array(CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $header
        );

        if ($gzip) {
            $opts[CURLOPT_ENCODING] = 'gzip';
        }

        /* 根据请求类型设置特定参数 */
        switch (strtoupper($method)) {
            case 'GET' :
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                $opts[CURLOPT_URL] = substr($opts[CURLOPT_URL], 0, -1);
                break;
            case 'POST' :
                //判断是否传输文件
                $params = http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default :
        }

        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if($error){
            $data = null;
        }
        return $data;
    }

    // 获取当前价格
    public static function getNowData() {
        $resArray = null;
        $url = 'http://47.92.138.223:2008/api/data/getNewPrice';


        $t = time();
        $sign = md5(self::$user . ':' . self::$passwd . ":$t");

        $url = sprintf('%s?username=%s&ts=%s&sign=%s&code=BTCCNY', $url, self::$user, $t, $sign);
        $res = self::curlfun($url);
//		Log::error($url . ' result: ' . $res);
        $resJson = empty($res) ? null : json_decode($res, true);
        if (empty($resJson)) {
            Log::error('getBitNowData return empty:' . $url);
            return $resArray;
        }

        if ($resJson['status'] != '1') {
            Log::error('getBitNowData return status error:' . $url);
            return $resArray;
        }

        if (empty($resJson['data'])) {
            Log::error('getBitNowData return data error:' . $url);
            return $resArray;
        }

        $resData = $resJson['data'];
        count($resData) >= 1 and $resData = $resData[0];
        $resArray['Price'] = round($resData['price'], 2);
        $resArray['Open'] = round($resData['open'], 2);
        $resArray['Close'] = round($resData['lastclose'], 2);
        $resArray['High'] = round($resData['high'], 2);
        $resArray['Low'] = round($resData['low'] ,2);
        $resArray['Diff'] = 0;
        $resArray['DiffRate'] = 0;
//		$resArray['Diff'] = $resData['diff'];
//		$resArray['DiffRate'] = $resData['diffrate'];
        return $resArray;
    }

    // 获取k线数据
    public static function getKData($type='1m') {
        if ($type == '1d') {
            return [];
        }
        $resArray = null;
        $url = 'http://47.92.138.223:2008/api/data/getKLinePrice';
        $t = time();
        $sign = md5(self::$user . ':' . self::$passwd . ":$t");

        $url = sprintf('%s?username=%s&ts=%s&sign=%s&type=%s&code=BTCCNY&rows=60', $url, self::$user, $t, $sign, $type);
        $res = self::curlfun($url, [], 'GET', true);
//		Log::error($url . ' result: ' . $res);
        $resJson = empty($res) ? null : json_decode($res, true);
        if (empty($resJson)) {
            Log::error('getBitKData return empty:' . $url);
            return $resArray;
        }

        if ($resJson['status'] != '1') {
            Log::error('getBitKData return status error:' . $url);
            return $resArray;
        }

        if (empty($resJson['data'])) {
            Log::error('getBitKData return data error:' . $url);
            return $resArray;
        }

        $resData = $resJson['data'];
        foreach ($resData as $row) {
            $resArray[] = array($row['starttime'], $row['open'], $row['close'], $row['high'], $row['low']);
        }
        return $resArray;
    }
}