<?php
/**
 * User: ikovalenko
 */

namespace AgentSIB\Diadoc\Model;


use AgentSIB\Diadoc\Exception\SignerProviderException;

class CloudOpensslSignerProvider implements SignerProviderInterface
{
    private $url;
    private $token;
    private $curlOptions;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    const RESOURCE_SIGN = '/sign';
    const RESOURCE_CHECK_SIGN = '/checkSign';
    const RESOURCE_ENCRYPT = '/encrypt';
    const RESOURCE_DECRYPT = '/decrypt';

    /**
     * CloudOpensslSignerProvider constructor.
     *
     * @param $url
     * @param $token
     * @param $curlOptions
     */
    public function __construct($url, $token, $curlOptions = [])
    {
        $this->url = $url;
        $this->token = $token;
        $this->curlOptions = $curlOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt($plainData)
    {
        $response = $this->doRequest(
            self::RESOURCE_ENCRYPT,
            [],
            self::METHOD_POST,
            [
                'data'  =>  base64_encode($plainData)
            ]
        );

        return base64_decode($response);
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt($encryptedData)
    {
        $response = $this->doRequest(
            self::RESOURCE_DECRYPT,
            [],
            self::METHOD_POST,
            [
                'data'  =>  base64_encode($encryptedData)
            ]
        );

        return base64_decode($response);
    }

    /**
     * {@inheritdoc}
     */
    public function sign($data)
    {
        $response = $this->doRequest(
            self::RESOURCE_SIGN,
            [],
            self::METHOD_POST,
            [
                'data'  =>  base64_encode($data)
            ]
        );

        return base64_decode($response);
    }

    /**
     * {@inheritdoc}
     */
    public function checkSign($data, $sign)
    {
        $response = $this->doRequest(
            self::RESOURCE_CHECK_SIGN,
            [],
            self::METHOD_POST,
            [
                'data'  =>  base64_encode($data),
                'sign'  =>  base64_encode($sign)
            ]
        );

        return $response === 'true';
    }

    protected function doRequest($resource, $params = [], $method = self::METHOD_GET, $data = array())
    {

        $uri = sprintf(
            '%s%s?%s',
            rtrim($this->url, '/'),
            $resource,
            http_build_query($params)
        );

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildRequestHeaders());


//        curl_setopt($ch, CURLOPT_HEADER, 1);
        if ($method == self::METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
        elseif ($method == self::METHOD_GET) {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        }

        curl_setopt_array($ch, $this->curlOptions);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new SignerProviderException(sprintf('Curl error: (%s) %s', curl_errno($ch), curl_error($ch)), curl_errno($ch));
        }
        if (!($httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE)) || ($httpCode !== 200 && $httpCode !== 204)) {
            throw new SignerProviderException(sprintf('Curl error http code: (%s) %s', $httpCode, $response), $httpCode);
        }
        curl_close($ch);

        return $response;
    }

    protected function buildRequestHeaders()
    {
        return ['X-Token: ' . $this->token];
    }

}