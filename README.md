# BackgroundServiceCaller

This is a implementation in PHP for using BackgroundService written in javascript. (BackgroundService is designed to run in node.js).

## Requirements
This class uses Fuse Array to handle the configuration.
- [PHPClasses](http://www.phpclasses.org/package/8706)
- [GitHub](https://github.com/ultrix3x/FuseArray)

The class uses JBackgroundService as the service manager.
- [JSClasses](http://www.jsclasses.org/package/358)
- [GitHub](https://github.com/ultrix3x/JBackgroundService)

## BackgroundServiceCaller

### Functions

#### AssignIni($ini)
Loads the configuration into an ConfigArray (an extension of the class FuseArray)

#### ConvertCharset($data, $fromCharset, $toCharset)
Convert the data in the mixed-type variable $data from the charset defined 
in $fromCharset to the charset defined in $toCharset.

#### Init()
Initialize the object.

Returns a string containing the name of the class used in the call.

#### UDPCall($service, $data, $charset = false)
Create a call to the service defined in configuration. The call is made over a datagram socket.

There is no response to this call.

#### TCPCall($service, $data, $charset = false)
Create a call to the service defined in configuration and wait for the response. The call is made over a tcp socket.

#### TCPAddQueue($service, $data, $charset = false)
Make a call to the tcp service. But instead of waiting for the result the process returns directly and this function returns aa string that contains the job id for the process on the server.

#### TCPCheckQueue($id)
This function uses the job id created by TCPAddQueue to check the status for the job.

If the function returns:
- 0 then the job has been created but not started. (This should not be possible since the status is changed from 0 to 1 before the TCPAddQueue returns.)
- 1 then the job has been started.
- 2 then the job is finished.

#### TCPGetQueue($id)
This function calls the job (defined by the id) and waits until the job is done (has the status 2).

## Using multiple service hosts
The class is written to use a single configuration. The configuration can use several different services since it connects the name of a service to a section of the configuration.

But there is a way to use multiple configurations.

By extending the base class BackgroundServiceCaller it is possible to load one configuration for each extended class. This is since the internal system uses the name of the called class to store the configuration internally.
```php
<?php
include('./bgcaller.php');
class DummyService1 extends BackgroundServiceCaller {}
class DummyService2 extends BackgroundServiceCaller {}
class DummyService3 extends BackgroundServiceCaller {}

BackgroundServiceCaller::AssignIni('./config0.ini');
DummyService1::AssignIni('./config1.ini');
DummyService2::AssignIni('./config2.ini');
DummyService3::AssignIni('./config3.ini');

// Now we can use four (4) different configurations.
```