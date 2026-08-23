<?php
/**
 * 阿里云开放 API RPC 签名客户端（手写实现，仅依赖 cURL，禁止官方 SDK）
 * 签名算法：HMAC-SHA1，按阿里云开放 API RPC 签名规范实现
 */
defined('APP_BOOT') or exit;

class AliyunRpc
{
    const ENDPOINT = 'https://dypnsapi.aliyuncs.com';
    const API_VERSION = '2017-05-25';

    private $accessKeyId;
    private $accessKeySecret;
    private $endpoint;
    /** @var string 最近一次错误 */
    private $error = '';

    /**
     * @param string $accessKeyId     RAM AccessKeyId
     * @param string $accessKeySecret RAM AccessKeySecret（不落日志）
     * @param string $endpoint        服务端点，缺省为号码认证服务 dypnsapi
     */
    public function __construct($accessKeyId, $accessKeySecret, $endpoint = self::ENDPOINT)
    {
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->endpoint = $endpoint;
    }

    /** 最近一次错误信息 */
    public function error()
    {
        return $this->error;
    }

    /**
     * 调用一个 RPC 风格动作
     *
     * @param string $action     动作名，如 SendSmsVerifyCode
     * @param array  $bizParams  业务参数（值为标量；null 值自动剔除）
     * @return array|null 解码后的响应数组；网络/签名异常返回 null
     */
    public function call($action, array $bizParams = array())
    {
        $params = array(
            'Action'           => $action,
            'Format'           => 'JSON',
            'Version'          => self::API_VERSION,
            'AccessKeyId'      => $this->accessKeyId,
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => $this->nonce(),
            'SignatureVersion' => '1.0',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
        );
        foreach ($bizParams as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = (string) $value;
            }
        }
        $params['Signature'] = $this->sign('POST', $params);

        $body = http_build_query($params);
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, // 显式固定主机名校验（默认值即 2，防运行环境被全局改写）
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->error = 'cURL 错误：' . $curlError;
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->error = '响应解析失败（HTTP ' . $httpCode . '）';
            return null;
        }
        return $data;
    }

    /**
     * 计算 RPC 签名
     * 规则：参数 ksort → 逐项 rawurlencode 并以 & 拼接 →
     * StringToSign = HTTPMethod&%2F&rawurlencode(规范化查询串) →
     * base64(hmac-sha1(StringToSign, AccessKeySecret&))
     *
     * @param string $method HTTP 方法
     * @param array  $params 全部请求参数（不含 Signature）
     * @return string
     */
    private function sign($method, array $params)
    {
        ksort($params);
        $canonical = '';
        foreach ($params as $key => $value) {
            $canonical .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
        }
        $canonical = substr($canonical, 1);
        $stringToSign = strtoupper($method) . '&%2F&' . rawurlencode($canonical);
        return base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
    }

    /** 签名随机数 */
    private function nonce()
    {
        return uniqid('', true) . random_int(1000, 9999);
    }
}
