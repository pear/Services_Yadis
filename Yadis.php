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
 * @category Services
 * @package  Services_Yadis
 * @author   Pádraic Brady <padraic.brady@yahoo.com>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @link     http://pear.php.net/Services_Yadis
 */

/**
 *  required files
 */
require_once 'Services/Yadis/Xrds/Service.php';
require_once 'Services/Yadis/Exception.php';
require_once 'Services/Yadis/Xrds/Namespace.php';
require_once 'Services/Yadis/Xri.php';
require_once 'HTTP/Request.php';
require_once 'Validate.php';

/**
 * Services_Yadis class
 *
 * Services_Yadis will provide a method of Service Discovery implemented
 * in accordance with the Yadis Specification 1.0. This describes a protocol
 * for locating an XRD document which details Services available. The XRD is
 * typically specific to a single user, identified by their Yadis ID.
 * Services_Yadis_XRDS will be a wrapper which is responsible for parsing
 * and presenting an iterable list of Services_Yadis_Service objects
 * holding the data for each specific Service discovered.
 *
 * Note that class comments cannot substitute for a full understanding of the
 * rules and nuances required to implement the Yadis protocol. Where doubt
 * exists, refer to the Yadis Specification 1.0 at:
 *      http://yadis.org/papers/yadis-v1.0.pdf
 * Departures from the specification should be regarded as bugs ;).
 *
 * Example usage:
 *
 *      Example 1: OpenID Service Discovery
 *
 *      $openid = 'http://padraic.astrumfutura.com';
 *      $yadis = new Services_Yadis($openid);
 *      $yadis->addNamespace('openid', 'http://openid.net/xmlns/1.0');
 *      $serviceList = $yadis->discover();
 *
 *      foreach ($serviceList as $service) {
 *          $types = $service->getTypes();
 *          echo $types[0], ' at ', implode(', ', $service->getUris()), PHP_EOL;
 *          echo 'Priority is ', $service->->getPriority(), PHP_EOL;
 *      }
 *
 *      Possible Result @index[0] (indicates we may send Auth 2.0 requests for
 *      OpenID):
 *
 *      http://specs.openid.net/auth/2.0/server at http://www.myopenid.com/server
 *      Priority is 0
 *
 * @category Services
 * @package  Services_Yadis
 * @author   Pádraic Brady <padraic.brady@yahoo.com>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @link     http://pear.php.net/Services_Yadis
 */
class Services_Yadis
{

    /**
     * Constants referring to Yadis response types
     */
    const XRDS_META_HTTP_EQUIV = 2;
    const XRDS_LOCATION_HEADER = 4;
    const XRDS_CONTENT_TYPE    = 8;

    /**
     * The current Yadis ID; this is the raw form initially submitted prior
     * to any transformation/validation as an URL. This *may* allow IRI support
     * in the future given IRIs map to URIs and adoption of the IRI standard
     * and are entering common use internationally.
     *
     * @var string
     */
    protected $yadisId = '';

    /**
     * The current Yadis URL; this is a URL either validated or transformed
     * from the initial Yadis ID. This URL is used to make the initial HTTP
     * GET request during Service Discovery.
     *
     * @var string
     */
    protected $yadisUrl = '';

    /**
     * Holds the first response received during Service Discovery.
     *
     * This is required to allow certain Service specific fallback methods.
     * For example, OpenID allows a Yadis fallback which relies on seeking a
     * set of appropriate <link> elements.
     *
     * @var HTTP_Request
     */
    protected $metaHttpEquivResponse = null;

    /**
     * A URL parsed from a HTML document's <meta> element inserted in
     * accordance with the Yadis Specification and which points to a Yadis
     * XRD document.
     *
     * @var string
     */
    protected $metaHttpEquivUrl = '';

    /**
     * A URI parsed from an X-XRDS-Location response-header. This value must
     * point to a Yadis XRD document otherwise the Yadis discovery process
     * should be considered to have failed.
     *
     * @var string
     */
    protected $xrdsLocationHeaderUrl = '';

    /**
     * Instance of Services_Yadis_Xrds_Namespace for managing namespaces
     * associated with an XRDS document.
     *
     * @var Services_Yadis_Xrds_Namespace
     */
    protected $namespace = null;

    /**
     * Array of valid HTML Content-Types. Required since Yadis states agents
     * must parse a document if received as the first response and with an
     * MIME type indicating HTML or XHTML. Listed in order of priority, with
     * HTML taking priority over XHTML.
     *
     * @link http://www.w3.org/International/articles/serving-xhtml/Overview.en.php
     * @var array
     */
    protected $validHtmlContentTypes = array(
        'text/html',
        'application/xhtml+xml',
        'application/xml',
        'text/xml'
    );

    /*
     * Array of characters which if found at the 0 index of a Yadis ID string
     * may indicate the use of an XRI.
     *
     * @var array
     */
    protected $xriIdentifiers = array(
        '=', '$', '!', '@', '+'
    );

    protected $httpRequestOptions = array();

    /**
     * HTTP_Request object utilised by this class if externally set
     *
     * @var HTTP_Request
     */
    protected $httpRequest = null;

    /**
     * Class Constructor
     *
     * Allows settings of the initial Yadis ID (an OpenID URL for example) and
     * an optional list of additional namespaces. For example, OpenID uses a
     * namespace such as: xmlns:openid="http://openid.net/xmlns/1.0"
     * Namespaces are assigned to a Services_Yadis_Xrds_Namespace container
     * object to be passed more easily to other objects being
     *
     * @param string $yadisId    Optional Yadis ID
     * @param array  $namespaces Optional array of namespaces
     *
     * @return void
     */
    public function __construct($yadisId = null, array $namespaces = array())
    {
        $this->namespace = new Services_Yadis_Xrds_Namespace;
        $this->addNamespaces($namespaces);
        if (isset($yadisId)) {
            $this->setYadisId($yadisId);
        }
    }

    /**
     * Set options to be passed to the PEAR HTTP_Request constructor
     *
     * @param array $options Array of options for HTTP_Request
     *
     * @return Services_Yadis
     */
    public function setHttpRequestOptions(array $options)
    {
        $this->httpRequestOptions = $options;
        return $this;
    }

    /**
     * Get options to be passed to the PEAR HTTP_Request constructor
     *
     * @return array
     */
    public function getHttpRequestOptions()
    {
        return $this->httpRequestOptions;
    }

    /**
     * A Yadis ID is usually an URL, but can also include an IRI, or XRI i-name.
     * The initial version will support URLs as standard before examining options
     * for supporting alternatives (IRI,XRI,i-name) since they require additional
     * validation and conversion steps (e.g. Punycode for IRI) before use.
     *
     * Note: The current Validate classes currently do not have complete IDNA
     * validation support for Internationalised Domain Names. To be addressed.
     *
     * @param string $yadisId The Yadis ID
     *
     * @return void
     */
    public function setYadisId($yadisId)
    {
        $this->yadisId = $yadisId;
        $this->setYadisUrl($yadisId);
    }

    /**
     * Returns the original Yadis ID string set for this class.
     *
     * @return string
     */
    public function getYadisId()
    {
        if (!isset($this->yadisId)) {
            throw new Services_Yadis_Exception(
                'No Yadis ID has been set on this object yet'
            );
        }
        return $this->yadisId;
    }

    /**
     * Attempts to create a valid URI based on the value of the parameter
     * which would typically be the Yadis ID.
     * Note: This currently only supports XRI transformations.
     *
     * @param string $yadisId The Yadis ID
     *
     * @return Services_Yadis
     * @throws Services_Yadis_Exception
     */
    public function setYadisUrl($yadisId)
    {
        /**
         * This step should validate IDNs (see ZF-881)
         */
        if (Validate::uri($yadisId)) {
            $this->yadisUrl = $yadisId;
            return $this;
        }

        /**
         * Check if the Yadis ID is an XRI
         */
        if (stripos($yadisId, 'xri://') === 0 ||
            in_array($yadisId[0], $this->xriIdentifiers)) {

            $xri = Services_Yadis_Xri::getInstance();

            $xri->setHttpRequestOptions($this->getHttpRequestOptions());
            $this->yadisUrl = $xri->setNamespace($this->namespace)->toUri($yadisId);
            return $this;
        }

        /**
         * The use of IRIs (International Resource Identifiers) is governed by
         * RFC 3490-3495. Not yet available for validation in PEAR.
         */
        throw new Services_Yadis_Exception(
            'Unable to validate a Yadis ID as a URI, '
            . 'or to transform a Yadis ID into a valid URI.'
        );
    }

    /**
     * Returns the Yadis URL. This will usually be identical to the Yadis ID,
     * unless the Yadis ID (in the future) was one of IRI, XRI or i-name which
     * required transformation to a valid URI.
     *
     * @return string
     */
    public function getYadisUrl()
    {
        if (!isset($this->yadisUrl)) {
            throw new Services_Yadis_Exception(
                'No Yadis ID/URL has been set on this object yet'
            );
        }
        return $this->yadisUrl;
    }

    /**
     * Add a list (array) of additional namespaces to be utilised by the XML
     * parser when it receives a valid XRD document.
     *
     * @param array $namespaces Array of namespaces
     *
     * @return  Services_Yadis
     */
    public function addNamespaces(array $namespaces)
    {
        $this->namespace->addNamespaces($namespaces);
        return $this;
    }

    /**
     * Add a single namespace to be utilised by the XML parser when it receives
     * a valid XRD document.
     *
     * @param string $namespace    The namespace name
     * @param string $namespaceUrl The namespace url
     *
     * @return Services_Yadis
     */
    public function addNamespace($namespace, $namespaceUrl)
    {
        $this->namespace->addNamespace($namespace, $namespaceUrl);
        return $this;
    }

    /**
     * Return the value of a specific namespace.
     *
     * @param string $namespace Namespace name
     *
     * @return string|null
     */
    public function getNamespace($namespace)
    {
        return $this->namespace->getNamespace($namespace);
    }

    /**
     * Returns an array of all currently set namespaces.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->namespace->getNamespaces();
    }

    /**
     * Performs Service Discovery, i.e. the requesting and parsing of a valid
     * Yadis (XRD) document into a list of Services and Service Data. The
     * return value will be an instance of Services_Yadis_Xrds which will
     * implement SeekableIterator. Returns FALSE on failure.
     *
     * @return Services_Yadis_Xrds|boolean
     * @throws Services_Yadis_Exception
     */
    public function discover()
    {
        $currentUri   = $this->getYadisUrl();
        $xrdsDocument = null;
        $request      = null;
        $xrdStatus    = false;

        // Check XRI first
        if (in_array($this->yadisId[0], $this->xriIdentifiers)) {
            $xri  = Services_Yadis_Xri::getInstance();
            $xrds = $xri->toCanonicalID($xri->getXri());
            return new Services_Yadis_Xrds_Service($xrds, $this->namespace);
        }

        while ($xrdsDocument === null) {
            $request = $this->get($currentUri);
            if (!$this->metaHttpEquivResponse) {
                $this->metaHttpEquivResponse = $request;
            }
            $responseType = $this->getResponseType($request);

            /**
             * If prior response type was a location header, or a http-equiv
             * content value, then it should have contained a valid URI to
             * an XRD document. Each of these when detected would set the
             * xrdStatus flag to true.
             */
            if (!$responseType == self::XRDS_CONTENT_TYPE && $xrdStatus == true) {
                throw new Services_Yadis_Exception(
                    'Yadis protocol could not locate a valid XRD document'
                );
            }

            /**
             * The Yadis Spec 1.0 specifies that we must use a valid response
             * header in preference to other responses. So even if we receive
             * an XRDS Content-Type, if it also includes an X-XRDS-Location
             * header we must request the Location URI and ignore the response
             * body.
             */
            switch($responseType) {
            case self::XRDS_LOCATION_HEADER:
                $xrdStatus  = true;
                $currentUri = $this->xrdsLocationHeaderUrl;
                break;
            case self::XRDS_META_HTTP_EQUIV:
                $xrdStatus  = true;
                $currentUri = $this->metaHttpEquivUrl;
                break;
            case self::XRDS_CONTENT_TYPE:
                $xrdsDocument = $request->getResponseBody();
                break;
            default:
                throw new Services_Yadis_Exception(
                    'Yadis protocol could not locate a valid XRD document'
                );
            }
        }

        try {
            $serviceList = $this->parseXrds($xrdsDocument);
        } catch (PEAR_Exception $e) {
            throw new Services_Yadis_Exception(
                'XRD Document could not be parsed with the following message: '
                . $e->getMessage(), $e->getCode());
        }
        return $serviceList;
    }

    /**
     * Return the very first response received when using a valid Yadis URL.
     * This is important for Services, like OpenID, which can attempt a
     * fallback solution in case Yadis fails, and the response came from a
     * user's personal URL acting as an alias.
     *
     * @return string|boolean
     */
    public function getUserResponse()
    {
        if ($this->metaHttpEquivResponse instanceof HTTP_Request) {
            return $this->metaHttpEquivResponse->getResponseBody();
        }
        return false;
    }

    /**
     * Setter for custom HTTP_Request type object
     *
     * @param HTTP_Request $request Instance of HTTP_Request
     *
     * @return void
     */
    public function setHttpRequest(HTTP_Request $request)
    {
        $this->httpRequest = $request;
    }

    /**
     * Gets the HTTP_Request object
     *
     * @return HTTP_Request
     */
    public function getHttpRequest()
    {
        return $this->httpRequest;
    }

    /**
     * Run any instance of HTTP_Request through a set of filters to
     * determine the Yadis Response type which in turns determines how the
     * response should be reacted to or dealt with.
     *
     * @param HTTP_Request $request Instance of HTTP_Request
     *
     * @return integer
     */
    protected function getResponseType(HTTP_Request $request)
    {
        if ($this->isXrdsLocationHeader($request)) {
            return self::XRDS_LOCATION_HEADER;
        } elseif ($this->isXrdsContentType($request)) {
            return self::XRDS_CONTENT_TYPE;
        } elseif ($this->isMetaHttpEquiv($request)) {
            return self::XRDS_META_HTTP_EQUIV;
        }
        return false;
    }

    /**
     * Use the HTTP_Request to issue an HTTP GET request carrying the
     * "Accept" header value of "application/xrds+xml". This can allow
     * servers to quickly respond with a valid XRD document rather than
     * forcing the client to follow the X-XRDS-Location bread crumb trail.
     *
     * @param string $url URL
     *
     * @return HTTP_Request
     */
    protected function get($url)
    {
        if ($this->getHttpRequest() === null) {
            $request = new HTTP_Request($url, $this->getHttpRequestOptions());
        } else {
            $request = $this->getHttpRequest();
        }
        $request->setMethod(HTTP_REQUEST_METHOD_GET);
        $request->addHeader('Accept', 'application/xrds+xml');
        $response = $request->sendRequest();
        if (PEAR::isError($response)) {
            throw new Services_Yadis_Exception(
                'Invalid response to Yadis protocol received: ' 
                . $request->getResponseCode() . ' ' . $request->getResponseBody()
            );
        }
        return $request;
    }

    /**
     * Checks whether the Response contains headers which detail where
     * we can find the XRDS resource for this user. If exists, the value
     * is set to the private $xrdsLocationHeaderUrl property.
     *
     * @param HTTP_Request $request Instance of HTTP_Request
     *
     * @return boolean
     */
    protected function isXrdsLocationHeader(HTTP_Request $request)
    {
        if ($request->getResponseHeader('x-xrds-location')) {
            $location = $request->getResponseHeader('x-xrds-location');
        } elseif ($request->getResponseHeader('x-yadis-location')) {
            $location = $request->getResponseHeader('x-yadis-location');
        }
        if (empty($location)) {
            return false;
        } elseif (!Validate::uri($location)) {
            throw new Services_Yadis_Exception(
                'Invalid URI found during Discovery for location of XRDS document:'
                . htmlentities($location, ENT_QUOTES, 'utf-8')
            );
        }
        $this->xrdsLocationHeaderUrl = $location;
        return true;
    }

    /**
     * Checks whether the Response contains the XRDS resource. It should, per
     * the specifications always be served as application/xrds+xml
     *
     * @param HTTP_Request $request Instance of HTTP_Request
     *
     * @return boolean
     */
    protected function isXrdsContentType(HTTP_Request $request)
    {
        if (!$request->getResponseHeader('Content-Type')
            || stripos($request->getResponseHeader('Content-Type'),
                       'application/xrds+xml') === false) {

            return false;
        }
        return true;
    }

    /**
     * Assuming this user is hosting a third party sourced identity under an
     * alias personal URL, we'll need to check if the website's HTML body
     * has a http-equiv meta element with a content attribute pointing to where
     * we can fetch the XRD document.
     *
     * @param HTTP_Request $request Instance of HTTP_Request
     *
     * @return boolean
     * @throws Services_Yadis_Exception
     */
    protected function isMetaHttpEquiv(HTTP_Request $request)
    {
        $location = null;
        if (!in_array($request->getResponseHeader('Content-Type'),
                      $this->validHtmlContentTypes)) {

            return false;
        }

        /**
         * Find a match for a relevant <meta> element, then iterate through the
         * results to see if a valid http-equiv value and matching content URI
         * exist.
         */
        $html = new DOMDocument();
        $html->loadHTML($request->getResponseBody());
        $head = $html->getElementsByTagName('head');
        if ($head->length > 0) {
            $metas = $head->item(0)->getElementsByTagName('meta');
            if ($metas->length > 0) {
                foreach ($metas as $meta) {
                    $equiv = strtolower($meta->getAttribute('http-equiv'));
                    if ($equiv == 'x-xrds-location'
                        || $equiv == 'x-yadis-location') {

                        $location = $meta->getAttribute('content');
                    }
                }
            }
        }

        if (is_null($location)) {
            return false;
        } elseif (!Validate::uri($location)) {
            throw new Services_Yadis_Exception(
                'The URI parsed from the HTML Alias document appears to be invalid, '
                . 'or could not be found: '
                . htmlentities($location, ENT_QUOTES, 'utf-8')
            );
        }
        /**
         * Should now contain the content value of the http-equiv type pointing
         * to an XRDS resource for the user's Identity Provider, as found by
         * passing the meta regex across the response body.
         */
        $this->metaHttpEquivUrl = $location;
        return true;
    }

    /**
     * Creates a new Services_Yadis_Xrds object which uses SimpleXML to
     * parse the XML into a list of Iterable Services_Yadis_Service
     * objects.
     *
     * @param string $xrdsDocument The plaintext XRDS document
     *
     * @return Services_Yadis_Xrds|boolean
     */
    protected function parseXrds($xrdsDocument)
    {
        $xrds = new SimpleXMLElement($xrdsDocument);
        return new Services_Yadis_Xrds_Service($xrds, $this->namespace);
    }

}
