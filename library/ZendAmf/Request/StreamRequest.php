<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Amf
 */

namespace ZendAmf\Request;

use ZendAmf\Constants;
use ZendAmf\Exception\InvalidArgumentException;
use ZendAmf\Exception\RuntimeException;
use ZendAmf\Parser\Amf0\Deserializer as Amf0Deserializer;
use ZendAmf\Parser\Amf3\Deserializer as Amf3Deserializer;
use ZendAmf\Parser\InputStream;
use ZendAmf\Parser;
use ZendAmf\Value;

/**
 * Handle the incoming AMF request by deserializing the data to php object
 * types and storing the data for Zend_Amf_Server to handle for processing.
 *
 * @todo       Currently not checking if the object needs to be Type Mapped to a server object.
 * @package    Zend_Amf
 */
class StreamRequest implements RequestInterface
{
    /**
     * @var int AMF client type (AMF0, AMF3)
     */
    protected $_clientType = 0; // default AMF0

    /**
     * @var array Message bodies
     */
    protected $_bodies = array();

    /**
     * @var array Message headers
     */
    protected $_headers = array();

    /**
     * @var int Message encoding to use for objects in response
     */
    protected $_objectEncoding = 0;

    /**
     * @var \ZendAmf\Parser\InputStream
     */
    protected $_inputStream;

    /**
     * @var Amf0Deserializer
     */
    protected $_deserializer;

    /**
     * Time of the request
     * @var  mixed
     */
    protected $_time;

    /**
     * Prepare the AMF InputStream for parsing.
     *
     * @param  string $request
     * @return \ZendAmf\Request\StreamRequest
     */
    public function initialize($request)
    {
        $this->_inputStream  = new Parser\InputStream($request);
        $this->_deserializer = new Amf0Deserializer($this->_inputStream);
        $this->readMessage($this->_inputStream);
        return $this;
    }

    /**
     * Takes the raw AMF input stream and converts it into valid PHP objects
     *
     * @param  \ZendAmf\Parser\InputStream
     * @return \ZendAmf\Request\StreamRequest
     * @throws RuntimeException
     */
    public function readMessage(Parser\InputStream $stream)
    {
        $clientVersion = $stream->readUnsignedShort();
        if (($clientVersion != Constants::AMF0_OBJECT_ENCODING)
            && ($clientVersion != Constants::AMF3_OBJECT_ENCODING)
            && ($clientVersion != Constants::FMS_OBJECT_ENCODING)
        ) {
            throw new RuntimeException('Unknown Player Version ' . $clientVersion);
        }

        $this->_bodies  = array();
        $this->_headers = array();
        $headerCount    = $stream->readInt();

        // Iterate through the AMF envelope header
        while ($headerCount--) {
            $this->_headers[] = $this->readHeader();
        }

        // Iterate through the AMF envelope body
        $bodyCount = $stream->readInt();
        while ($bodyCount--) {
            $this->_bodies[] = $this->readBody();
        }

        return $this;
    }

    /**
     * Deserialize a message header from the input stream.
     *
     * A message header is structured as:
     * - NAME String
     * - MUST UNDERSTAND Boolean
     * - LENGTH Int
     * - DATA Object
     *
     * @return \ZendAmf\Value\MessageHeader
     * @throws RuntimeException
     */
    public function readHeader()
    {
        $name     = $this->_inputStream->readUTF();
        $mustRead = (bool)$this->_inputStream->readByte();
        $length   = $this->_inputStream->readLong();

        try {
            $data = $this->_deserializer->readTypeMarker();
        } catch (\Exception $e) {
            throw new RuntimeException('Unable to parse ' . $name . ' header data: ' . $e->getMessage() . ' '. $e->getLine(), 0, $e);
        }

        $header = new Value\MessageHeader($name, $mustRead, $data, $length);
        return $header;
    }

    /**
     * Deserialize a message body from the input stream
     *
     * @return \ZendAmf\Value\MessageBody
     * @throws RuntimeException
     */
    public function readBody()
    {
        $targetURI   = $this->_inputStream->readUTF();
        $responseURI = $this->_inputStream->readUTF();
        $length      = $this->_inputStream->readLong();

        try {
            $data = $this->_deserializer->readTypeMarker();
        } catch (\Exception $e) {
            throw new RuntimeException('Unable to parse ' . $targetURI . ' body data ' . $e->getMessage(), 0, $e);
        }

        // Check for AMF3 objectEncoding
        if ($this->_deserializer->getObjectEncoding() == Constants::AMF3_OBJECT_ENCODING) {
            /*
             * When and AMF3 message is sent to the server it is nested inside
             * an AMF0 array called Content. The following code gets the object
             * out of the content array and sets it as the message data.
             */
            if(is_array($data) && $data[0] instanceof Value\Messaging\AbstractMessage){
                $data = $data[0];
            }

            // set the encoding so we return our message in AMF3
            $this->_objectEncoding = Constants::AMF3_OBJECT_ENCODING;
        }

        $body = new Value\MessageBody($targetURI, $responseURI, $data);
        return $body;
    }

    /**
     * Return an array of the body objects that were found in the amf request.
     *
     * @return \ZendAmf\Value\MessageBody[] {target, response, length, content}
     */
    public function getAmfBodies()
    {
        return $this->_bodies;
    }

    /**
     * Accessor to private array of message bodies.
     *
     * @param  \ZendAmf\Value\MessageBody $message
     * @return \ZendAmf\Request\StreamRequest
     */
    public function addAmfBody(Value\MessageBody $message)
    {
        $this->_bodies[] = $message;
        return $this;
    }

    /**
     * Return an array of headers that were found in the amf request.
     *
     * @return array {operation, mustUnderstand, length, param}
     */
    public function getAmfHeaders()
    {
        return $this->_headers;
    }

    /**
     * Return the either 0 or 3 for respect AMF version
     *
     * @return int
     */
    public function getObjectEncoding()
    {
        return $this->_objectEncoding;
    }

    /**
     * Set the object response encoding
     *
     * @param  mixed $int
     * @return \ZendAmf\Request\StreamRequest
     */
    public function setObjectEncoding($int)
    {
        $this->_objectEncoding = $int;
        return $this;
    }
}
