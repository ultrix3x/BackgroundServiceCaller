<?php
class BackgroundServiceCaller {
  protected static $encFunc = null;
  protected static $config = array();
  
  public static function AssignIni($ini) {
    if(!class_exists('ConfigArray')) {
      if(is_file(__DIR__.'/extends/configarray.php')) {
        include_once(__DIR__.'/extends/configarray.php');
      } elseif(is_file(__DIR__.'/../FuseArray/extends/configarray.php')) {
        include_once(__DIR__.'/../FuseArray/extends/configarray.php');
      }
    }
    $class = get_called_class();
    if(!isset(static::$config[$class])) {
      if(class_exists('ConfigArray')) {
        static::$config[$class] = ConfigArray::ConvertIni($ini);
      } else {
        throw new Exception('Missing FuseArray. Please include it into project');
      }
    }
  }
  
  public static function ConvertFromCharset($data, $charset) {
    if(is_string($data)) {
      return static::$encFunc($data, $charset, 'utf-8');
    } elseif(is_array($data) || ($data instanceof ArrayAccess)) {
      $newData = array();
      foreach($data as $key => $value) {
        $key = static::$encFunc($key, $charset, 'utf-8');
        $newData[$key] = static::ConvertFromCharset($value);
      }
      return $newData;
    }
  }
  
  public static function Init() {
    if(static::$config === null) {
      throw new Exception('No config available. Please call '.  get_called_class().'::AssignIni() before calling this function.');
    }
    if(static::$encFunc === false) {
      if(function_exists('mb_convert_encoding')) {
        static::$encFunc = function($data, $fromEncoding, $toEncoding) {
          return mb_convert_encoding($data, $toEncoding, $fromEncoding);
        };
      } elseif(function_exists('iconv')) {
        static::$encFunc = function($data, $fromEncoding, $toEncoding) {
          return iconv($fromEncoding, $toEncoding, $data);
        };
      }
    }
  }
  
  public static function UDPCall($service, $data, $charset = false) {
    static::Init();
    if($charset !== false) {
      $data = static::ConvertFromCharset($data, $charset, 'utf-8');
    }
    $class = get_called_class();
    $url = static::$config[$class]->Get($service.'.'.'udpurl');
    if($url === null) {
      throw new Exception('No url defined for this UDP Service ('.$service.')');
    }
    $fp = @stream_socket_client($url, $errno, $errstr);
    if($fp) {
      @socket_set_timeout($fp, 0, 50);
      if($data !== null) {
        @fwrite($fp, $service.':'.$data);
      }
      @fclose($fp);
    }
  }
  
  public static function TCPCall($service, $data, $charset = false) {
    static::Init();
    if($charset !== false) {
      $data = static::ConvertFromCharset($data, $charset, 'utf-8');
    }
    $class = get_called_class();
    $url = static::$config[$class]->Get($service.'.'.'tcpurl');
    if($url === null) {
      throw new Exception('No url defined for this TCP Service ('.$service.')');
    }
    $fp = @fsockopen($url);
    if($fp) {
      fputs($fp, $service.':'.$data);
      $result = '';
      while(!feof($fp)) {
        $line = @fgets($fp, 1024);
        $result .= $line;
      }
      fclose($fp);
      if(!preg_match('/^[45][0-9]{2}\:/Us', ltrim($result))) {
        if($charset !== false) {
          $result = static::ConvertFromCharset($data, 'utf-8', $charset);
        }
        return $result;
      }
    } else {
      throw new Exception('Can\'t open url');
    }
    return null;
  }
  
  public static function TCPAddQueue($service, $data, $charset = false) {
    $id = static::TCPCall('queue', 'add:'.$service.':'.$data, $charset);
    return $id;
  }
  
  public static function TCPCheckQueue($id) {
    $data = static::TCPCall('queue', 'check:'.$id);
    return $data;
  }
  
  public static function TCPGetQueue($id) {
    $data = static::TCPCall('queue', 'get:'.$id);
    return $data;
  }
  
}