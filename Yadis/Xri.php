<?php
/**
 * Implementation of the Yadis Specification 1.0 protocol for service
 * discovery from an Identity URI/XRI or other.
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2007 Pádraic Brady <padraic.brady@yahoo.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * The name of the author may not be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Services
 * @package    Services_Yadis
 * @author     Pádraic Brady (http://blog.astrumfutura.com)
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    $Id$
 */

/** HTTP_Request */
require_once 'HTTP/Request.php';

/** Validate */
require_once 'Validate.php';

/**
 * Provides methods for translating an XRI into a URI.
 *
 * @category   Services
 * @package    Services_Yadis
 * @author     Pádraic Brady (http://blog.astrumfutura.com)
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 */
class Services_Yadis_Xri
{

    /**
     * Hold an instance of this object per the Singleton Pattern.
     *
     * @var Services_Yadis_Xri
     */
    protected static $_instance = null;

    /*
     * Array of characters which if found at the 0 index of a Yadis ID string
     * may indicate the use of an XRI.
     *
     * @var array
     */
    protected $_xriIdentifiers = array(
        '=', '$', '!', '@', '+'
    );

    /**
     * Default proxy to append XRI identifier to when forming a valid URI.
     *
     * @var string
     */
    protected $_proxy = 'http://xri.net/';

    /**
     * Instance of Services_Yadis_Xrds_Namespace for managing namespaces
     * associated with an XRDS document.
     *
     * @var Services_Yadis_Xrds_Namespace
     */
    protected $_namespace = null;

    /**
     * The XRI string.
     *
     * @var string
     */
    protected $_xri = null;

    /**
     * The URI as translated from an XRI and appended to a Proxy.
     *
     * @var string
     */
    protected $_uri = null;

    /**
     * A Canonical ID if requested, and parsed from the XRDS document found
     * by requesting the URI created from a valid XRI.
     *
     * @var string
     */
    protected $_canonicalId = null;

    protected $_httpRequestOptions = null;

    /**
     * Constructor; protected since this class is a singleton.
     */
    protected function __construct()
    {}

    /**
     * Return a singleton instance of this class.
     *
     * @return  Services_Yadis_Xri
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * Set a Namespace object which contains all relevant namespaces
     * for XPath queries on this Yadis resource.
     *
     * @param Services_Yadis_Xrds_Namespace
     * @return Services_Yadis_Xri
     */
    public function setNamespace(Services_Yadis_Xrds_Namespace $namespace)
    {
        $this->_namespace = $namespace;
        return $this;
    }

    /**
     * Set an XRI proxy URI. A default of "http://xri.net/" is available.
     *
     * @param   string $proxy
     * @return  Services_Yadis_Xri
     * @throws  Services_Yadis_Exception
     */
    public function setProxy($proxy)
    {
        if (!Validate::uri($proxy)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('Invalid URI; unable to set as an XRI proxy');
        }
        $this->_proxy = $proxy;
        return $this;
    }

    /**
     * Return the URI of the current proxy.
     *
     * @param string $proxy
     */
    public function getProxy()
    {
        return $this->_proxy;
    }

    /**
     * Set an XRI to be translated to a URI.
     *
     * @param  string $url
     * @return Services_Yadis_Xri
     * @throws Services_Yadis_Exception
     */
    public function setXri($xri)
    {
        /**
         * Check if the passed string is a likely XRI.
         */
        if (stripos($xri, 'xri://') === false && !in_array($xri[0], $this->_xriIdentifiers)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('Invalid XRI string submitted');
        }
        $this->_xri = $xri;
        return $this;
    }

    /**
     * Return the original XRI string.
     *
     * @return string
     */
    public function getXri()
    {
        return $this->_xri;
    }

    /**
     * Attempts to convert an XRI into a URI. In simple terms this involves
     * removing the "xri://" prefix and appending the remainder to the URI of
     * an XRI proxy such as "http://xri.net/".
     *
     * @param  string $xri
     * @return string
     * @throws Services_Yadis_Exception
     * @uses Validate
     */
    public function toUri($xri = null, $serviceType = null)
    {
        if (!is_null($serviceType)) {
            $this->_serviceType = (string) $serviceType;
        }
        if (isset($xri)) {
            $this->setXri($xri);
        }
        /**
         * Get rid of the xri:// prefix before assembling the URI
         * including any IP or DNS wildcards
         */
        if (stripos($this->_xri, 'xri://') == 0) {
            if (stripos($this->_xri, 'xri://$ip*') == 0) {
                $iname = substr($xri, 10);
            } elseif (stripos($this->_xri, 'xri://$dns*') == 0) {
                $iname = substr($xri, 11);
            } else {
                $iname = substr($xri, 6);
            }
        } else {
            $iname = $xri;
        }
        $uri = $this->getProxy() . $iname;
        if (!Validate::uri($uri)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('Unable to translate XRI to a valid URI using proxy: ' . $this->getProxy());
        }
        $this->_uri = $uri;
        return $uri;
    }

    /**
     * Based on an XRI, will request the XRD document located at the proxy
     * prefixed URI and parse in search of the XRI Canonical Id. This is
     * a flexible requirement. OpenID 2.0 requires the use of the Canonical
     * ID instead of the raw i-name. 2idi.com, on the other hand, does not.
     *
     * @todo Imcomplete; requires interface from Yadis main class
     * @param string $xri
     * @return string
     * @throws Services_Yadis_Exception
     */
    public function toCanonicalId($xri = null)
    {
        if (!isset($xri) && !isset($this->_uri)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('No XRI passed as parameter as required unless called after Services_Yadis_Xri:toUri');
        } elseif (isset($xri)) {
            $uri = $this->toUri($xri);
        } else {
            $uri = $this->_uri;
        }

        $request = $this->_get($uri, $this->getHttpRequestOptions());
        if (stripos($request->getResponseHeader('Content-Type'), 'application/xrds+xml') === false) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('The response header indicates the response body is not an XRDS document');
        }

        $xrds = new SimpleXMLElement($request->getResponseBody());
        $this->_namespace->registerXpathNamespaces($xrds);
        $this->_canonicalId = $xrds->xpath('/xrd:CanonicalID[last()]');
        if (!$this->_canonicalId) {
            return false;
        }
        throw new Exception('Not yet implemented');
        //var_dump($canonicalIds . __FILE__.__LINE__); exit;
        return $this->_canonicalId;
    }

    public function getCanonicalId()
    {
        if (!is_null($this->_canonicalId)) {
            return $this->_canonicalId;
        }
        if (is_null($this->_xri)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('Unable to get a Canonical Id since no XRI value has been set');
        }
        return $this->toCanonicalId($this->_xri);
    }

        /**
     * Set options to be passed to the PEAR HTTP_Request constructor
     *
     * @param array $options
     * @return void
     */
    public function setHttpRequestOptions(array $options)
    {
        $this->_httpRequestOptions = $options;
    }

    /**
     * Get options to be passed to the PEAR HTTP_Request constructor
     *
     * @return array
     */
    public function getHttpRequestOptions()
    {
        return $this->_httpRequestOptions;
    }

    /**
     * Required to request the root i-name (XRI) XRD which will provide an
     * error message that the i-name does not exist, or else return a valid
     * XRD document containing the i-name's Canonical ID.
     *
     * @param   string $uri
     * @return  HTTP_Request
     * @todo    Finish this a bit better using the QXRI rules.
     */
    protected function _get($url, $serviceType = null, array $options = null)
    {
        $request = new HTTP_Request($url, $options);
        $request->setMethod(HTTP_REQUEST_METHOD_GET);
        $request->addHeader('Accept', 'application/xrds+xml');
        if ($serviceType) {
            $request->addQueryString('_xrd_r', 'application/xrds+xml');
            $request->addQueryString('_xrd_t', $serviceType);
        } else {
            $request->addQueryString('_xrd_r', 'application/xrds+xml;sep=false');
        }

        $response = $request->sendRequest();
        if (PEAR::isError($response)) {
            require_once 'Services/Yadis/Exception.php';
            throw new Services_Yadis_Exception('Invalid response to Yadis protocol received: ' . $request->getResponseCode() . ' ' . $request->getResponseBody());
        }

        return $request;
    }

}