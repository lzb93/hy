<?php
namespace app\common;

use think\Log;

class AesUtil {
    protected $cipher;
    protected $mode;
    protected $pad_method;
    protected $secret_key;
    protected $iv;

    public function __construct($key, $method = 'pkcs5', $iv = '', $mode = MCRYPT_MODE_ECB, $cipher = MCRYPT_RIJNDAEL_128)
    {
        $this->secret_key = $key;
        $this->pad_method = $method;
        $this->iv = $iv;
        $this->mode = $mode;
        $this->cipher = $cipher;
    }

    protected function pad_or_unpad($str, $ext)
    {
        if (!is_null($this->pad_method)) {
            $func_name = __CLASS__ . '::' . $this->pad_method . '_' . $ext . 'pad';
            if (is_callable($func_name)) {
                $size = mcrypt_get_block_size($this->cipher, $this->mode);
                return call_user_func($func_name, $str, $size);
            }
        }
        return $str;
    }

    protected function pad($str)
    {
        return $this->pad_or_unpad($str, '');
    }

    protected function unpad($str)
    {
        return $this->pad_or_unpad($str, 'un');
    }

    public function encrypt($str, $encType=1) {
        $rt = '';
        try {
            $str = $this->pad($str);
            $td = mcrypt_module_open($this->cipher, '', $this->mode, '');
            if (empty($this->iv)) {
                $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            } else {
                $iv = $this->iv;
            }

            mcrypt_generic_init($td, $this->secret_key, $iv);
            $cyper_text = mcrypt_generic($td, $str);
            switch ($encType) {
                case 2:// base64编码
                    $rt = base64_encode($cyper_text);
                    break;
                default: // 默认转出16进制，并且大写
                    $rt = strtoupper(bin2hex($cyper_text));
            }
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } catch (\Exception $e) {
            Log::error('AES加密错误：' . $e->getMessage());
        }
        return $rt;
    }

    public function decrypt($str, $encType=1) {
        try {
            $td = mcrypt_module_open($this->cipher, '', $this->mode, '');
            if (empty($this->iv)) {
                $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            } else {
                $iv = $this->iv;
            }

            switch ($encType) {
                case 2:// base64解码
                    $data = base64_decode($str);
                    break;
                default: // 默认转出16进制，并且大写
                    $data = hex2bin($str);
            }

            mcrypt_generic_init($td, $this->secret_key, $iv);
            $decrypted_text = mdecrypt_generic($td, $data);
            $rt = $decrypted_text;
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
            return $this->unpad($rt);
        } catch (\Exception $e) {
            Log::error('AES解密错误：' . $e->getMessage());
            return '';
        }
    }

    public static function pkcs5_pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);

        return $text . str_repeat(chr($pad), $pad);
    }

    public static function pkcs5_unpad($text)
    {
        $pad = ord($text[strlen($text) - 1]);

        if ($pad > strlen($text)) return false;

        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;

        return substr($text, 0, -1 * $pad);
    }
}