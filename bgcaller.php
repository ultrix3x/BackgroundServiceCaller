<?php
class BackgroundServiceCaller {
  protected static $encFunc = null;
  protected static $config = array();
  
  /**
   * Assign a configuration to the class
   * @param type $ini ini data as either a string or a filename
   * @throws Exception if the FuseArray class is missing
   */
  public static function AssignIni($ini) {
    // If the class ConfigArray doesn't exist then try to load it
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
        // If the class exist then load the ini-data
        static::$config[$class] = ConfigArray::ConvertIni($ini);
      } else {
        // It the class doesn't exist then throw an exception
        throw new Exception('Missing FuseArray. Please include it into project');
      }
    }
  }
  
  /**
   * Convert data from  one charset to another
   * @param mixed $data the data to convert
   * @param type $from the charset to convert from
   * @param type $to the charset to conver to
   * @return mixed data in the charset defined by $to
   */
  public static function ConvertCharset($data, $from, $to) {
    if(is_string($data)) {
      // If data is a string then convert
      return static::$encFunc($data, $from, $to);
    } elseif(is_array($data) || ($data instanceof ArrayAccess)) {
      // If data is an array then loop through the array and convert each part
      $newData = array();
      foreach($data as $key => $value) {
        $key = static::$encFunc($key, $from, $to);
        $newData[$key] = static::ConvertCharset($value, $charset);
      }
      return $newData;
    }
  }
  
  /**
   * Initialize the class and setup the encoding functions
   * @return string current class name
   * @throws Exception when there is no configuration loaded
   */
  public static function Init() {
    // Get the called class
    // This makes it possible to keep different configurations, one for
    // each extended class.
    $class = get_called_class();
    if(!isset(static::$config[$class])) {
      // If the configuration for this class has been loaded then throw
      // an exception
      throw new Exception('No config available. Please call '.get_called_class().'::AssignIni() before calling this function.');
    }
    // If the encoding function has been assigned then do so.
    if(static::$encFunc === false) {
      // Use mb_convert_encoding if that exists, otherwise use iconv if
      // that exists
      if(function_exists('mb_convert_encoding')) {
        // Create an anonymous function that calls mb_convert_encoding
        static::$encFunc = function($data, $fromEncoding, $toEncoding) {
          return mb_convert_encoding($data, $toEncoding, $fromEncoding);
        };
      } elseif(function_exists('iconv')) {
        // Createan anonymous function that calls iconv
        static::$encFunc = function($data, $fromEncoding, $toEncoding) {
          return iconv($fromEncoding, $toEncoding, $data);
        };
      }
    }
    // Return the class name
    return $class;
  }
  
  /**
   * Make an datagram call to the service
   * @param string $service the name of the service
   * @param string $data the data to send to the service
   * @param string|bool $charset the charset to use for the request. If
   *           this is set to false then utf-8 is used
   * @throws Exception if the configuration doesn't contain a tcpurl for
   *           the service or if the service can't be opened.
   */
  public static function UDPCall($service, $data, $charset = false) {
    // Call the Init to initialize and to get the current class name
    $class = static::Init();
    if($charset !== false) {
      // If the charset is set then convert the data
      $data = static::ConvertCharset($data, $charset, 'utf-8');
    }
    // Get the url to use from the configuration
    $url = static::$config[$class]->Get($service.'.'.'udpurl');
    // If the url doesn't exist in the configuration
    if($url === null) {
      // then throw an exception
      throw new Exception('No url defined for this UDP Service ('.$service.')');
    }
    // Open a datagram socket
    $fp = @stream_socket_client($url, $errno, $errstr);
    if($fp) {
      // Set the timeout to 50 ms
      @socket_set_timeout($fp, 0, 50);
      if($data !== null) {
        // If there is data to to write it by combining the name of the 
        // service and the data combined by at colon (:)
        @fwrite($fp, $service.':'.$data);
      } else {
        // Otherwise just write the service name amd a colon (:)
        @fwrite($fp, $service.':');
      }
      // Close the connection
      @fclose($fp);
    } else {
      // IF the socket couldn't be opend then throw an exception
      throw new Exception('Can\'t open url');
    }
  }
  
  /**
   * Make a tcp call to the service
   * @param string $service the name of the service
   * @param string $data the data to send to the service
   * @param string|bool $charset the charset to use for the request. If
   *           this is set to false then utf-8 is used
   * @return string the id for the job prefixed with "id:"
   * @throws Exception if the configuration doesn't contain a tcpurl for
   *           the service or if the service can't be opened.
   */
  public static function TCPCall($service, $data, $charset = false) {
    // Call the Init to initialize and to get the current class name
    $class = static::Init();
    if($charset !== false) {
      // If the charset is set then convert the data
      $data = static::ConvertCharset($data, $charset, 'utf-8');
    }
    // Get the url to use from the configuration
    $url = static::$config[$class]->Get($service.'.'.'tcpurl');
    // If the url doesn't exist in the configuration
    if($url === null) {
      // then throw an exception
      throw new Exception('No url defined for this TCP Service ('.$service.')');
    }
    // Open a tcp socket
    $fp = @fsockopen($url);
    if($fp) {
      // If the socket could be opened then write data to it
      // combine the name of the service and the data combined by at colon
      fputs($fp, $service.':'.$data);
      // Set the output variable to an empty string
      $result = '';
      // While the socket is open then read data
      while(!feof($fp)) {
        // Read string of maximum 1024 bytes
        $line = @fgets($fp, 1024);
        // and add it to the output variable
        $result .= $line;
      }
      // When the socket has been closed by the server then close the file
      fclose($fp);
      // If the result begins with a number between 400-599 followed by a
      // colon then the service has returned an error
      if(!preg_match('/^[45][0-9]{2}\:/Us', ltrim($result))) {
        if($charset !== false) {
          $result = static::ConvertCharset($data, 'utf-8', $charset);
        }
        return $result;
      }
    } else {
      throw new Exception('Can\'t open url');
    }
    return null;
  }
  
  /**
   * Add the job to the defined service with some data
   * @param string $service
   * @param string $data
   * @param string|bool $charset
   * @return string
   */
  public static function TCPAddQueue($service, $data, $charset = false) {
    $id = static::TCPCall('queue', 'add:'.$service.':'.$data, $charset);
    return $id;
  }
  
  /**
   * Check the status of the given job (by id)
   * Returns a numeric string
   *  0 = Job has been registered but not yet started
   *  1 = Job has been started
   *  2 = Job is done
   * @param string $id
   * @return string
   */
  public static function TCPCheckQueue($id) {
    $data = static::TCPCall('queue', 'check:'.$id);
    return $data;
  }
  
  /**
   * Get the result for the given job (by id)
   * The function will wait until the job is finished.
   * @param string $id
   * @return string
   */
  public static function TCPGetQueue($id) {
    $data = static::TCPCall('queue', 'get:'.$id);
    return $data;
  }
  
}