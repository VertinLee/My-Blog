<?php
/**
 * 纯 PHP SMTP 客户端：stream_socket_client 实现 EHLO/AUTH LOGIN/SSL/STARTTLS
 * 零外部依赖（禁止 Composer/Guzzle），仅供 smtp-mailer 插件使用
 */
defined('APP_BOOT') or exit;

class SmtpClient
{
    /** @var resource|null 套接字 */
    private $sock = null;
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromName;
    /** @var string 最近一次错误 */
    private $error = '';

    /**
     * @param string $host       SMTP 主机
     * @param int    $port       端口（25/465/587）
     * @param string $encryption none/ssl/tls
     * @param string $username   账号（通常即发件地址）
     * @param string $password   授权码（不落日志）
     * @param string $fromName   发件人名称
     */
    public function __construct($host, $port, $encryption, $username, $password, $fromName)
    {
        $this->host = $host;
        $this->port = (int) $port;
        $this->encryption = $encryption;
        $this->username = $username;
        $this->password = $password;
        $this->fromName = $fromName;
    }

    /** 最近一次错误信息 */
    public function error()
    {
        return $this->error;
    }

    /**
     * 发送邮件（纯文本正文）
     *
     * @param string $to      收件人
     * @param string $subject 主题（UTF-8）
     * @param string $body    正文
     * @return bool
     */
    public function send($to, $subject, $body)
    {
        if (!$this->connect()) {
            return false;
        }
        $ok = $this->transmit($to, $subject, $body);
        $this->quit();
        return $ok;
    }

    /** 建立连接并完成握手与认证 */
    private function connect()
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';
        // 连接失败的告警由内核错误处理器记录，失败原因经 $errstr 落入 $this->error
        $this->sock = stream_socket_client($remote, $errno, $errstr, 15);
        if (!$this->sock) {
            $this->error = '连接失败：' . $errstr;
            return false;
        }
        stream_set_timeout($this->sock, 15);
        if (!$this->expect('220')) {
            return false;
        }
        if (!$this->hello()) {
            return false;
        }
        if ($this->encryption === 'tls') {
            if (!$this->cmd('STARTTLS', '220')) {
                return false;
            }
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                $this->error = 'STARTTLS 协商失败';
                return false;
            }
            if (!$this->hello()) {
                return false;
            }
        }
        if ($this->username !== '') {
            if (!$this->cmd('AUTH LOGIN', '334')
                || !$this->cmd(base64_encode($this->username), '334')
                || !$this->cmd(base64_encode($this->password), '235')) {
                return false;
            }
        }
        return true;
    }

    /** EHLO 失败时回退 HELO */
    private function hello()
    {
        if ($this->cmd('EHLO ' . gethostname(), '250')) {
            return true;
        }
        return $this->cmd('HELO ' . gethostname(), '250');
    }

    /** 发送报文 */
    private function transmit($to, $subject, $body)
    {
        if (!$this->cmd('MAIL FROM:<' . $this->username . '>', '250')) {
            return false;
        }
        if (!$this->cmd('RCPT TO:<' . $to . '>', '250')) {
            return false;
        }
        if (!$this->cmd('DATA', '354')) {
            return false;
        }
        $headers = 'Date: ' . date('r') . "\r\n"
            . 'From: =?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->username . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . self::encodeHeader($subject) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n";
        $payload = $headers . "\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
        // 点号转义（dot-stuffing）防止正文提前结束 DATA
        $payload = preg_replace('/^\./m', '..', $payload);
        return $this->cmd($payload . "\r\n.", '250');
    }

    /** 结束会话 */
    private function quit()
    {
        if (is_resource($this->sock)) {
            // 收尾写失败无实际影响，告警由内核错误处理器记录
            fwrite($this->sock, "QUIT\r\n");
            fclose($this->sock);
            $this->sock = null;
        }
    }

    /**
     * 发送一条命令并校验响应码
     *
     * @param string $command 命令
     * @param string $expect  期望响应码前缀（3 位）
     * @return bool
     */
    private function cmd($command, $expect)
    {
        if (!fwrite($this->sock, $command . "\r\n")) {
            $this->error = '写入失败';
            return false;
        }
        return $this->expect($expect);
    }

    /** 读取多行响应并校验响应码 */
    private function expect($expect)
    {
        $response = '';
        while (!feof($this->sock)) {
            $line = fgets($this->sock, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // 多行响应的中间行形如 "250-xxx"，末行形如 "250 xxx"
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }
        if (strpos($response, $expect) !== 0) {
            $this->error = '响应异常：' . trim($response);
            return false;
        }
        return true;
    }

    /** 邮件头 UTF-8 编码 */
    private static function encodeHeader($text)
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
