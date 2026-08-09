<?php
/**
 * 配置加载器：读取站点根目录 config.php（由安装程序生成，不入库）
 */
defined('APP_BOOT') or exit;

class Config
{
    /** @var array 配置项 */
    private static $data = array();

    /**
     * 加载配置文件（返回数组形式）
     *
     * @param string $file 配置文件绝对路径
     * @return bool 是否加载成功
     */
    public static function load($file)
    {
        if (!is_file($file)) {
            return false;
        }
        $data = require $file;
        if (!is_array($data)) {
            return false;
        }
        self::$data = $data;
        return true;
    }

    /**
     * 读取配置项，支持点号路径，如 db.host
     *
     * @param string $key     配置键
     * @param mixed  $default 缺省值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
