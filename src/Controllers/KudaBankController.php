<?php

namespace Giftbalogun\Kudaencryption\Controllers;

use GuzzleHttp\Psr7\Response;
use Illuminate\Routing\Controller;
use Giftbalogun\Kudaencryption\KudaEncryption;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use phpseclib\Crypt\Random;
use Giftbalogun\Kudaencryption\Controllers\ServiceTypes;

class KudabankController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function __construct()
    {
        $this->privateKey = __DIR__ . '/../xml/private.pem';
        $this->publicKey = __DIR__ . '/../xml/public.pem';
        $this->clientKey = 'fbk2GVvFoCZe1xdLK740';
        //$this->clientKey = '17qg40rf29eNQp8RGDyY';
        $this->crypter = new KudaEncryption();
        //$this->baseUri = 'https://kuda-openapi-uat.kudabank.com/v1';
        $this->baseUri = 'https://kuda-openapi.kuda.com/v1';
    }

    public function getAccountInfo($payload, $requestRef = null)
    {
        return $this->makeRequest(
            ServiceTypes::NAME_ENQUIRY,
            $payload,
            $requestRef
        );
    }

    public function getBankList($requestRef = null)
    {
        return $this->makeRequest(ServiceTypes::BANK_LIST, [], $requestRef);
    }

    public function create_virtual_account(array $data, $requestRef)
    {
        $servicetype = ServiceTypes::ADMIN_CREATE_VIRTUAL_ACCOUNT;
        $payload = $data;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function getadminbalance(array $data, $requestRef)
    {
        $servicetype = ServiceTypes::ADMIN_RETRIEVE_MAIN_ACCOUNT_BALANCE;
        $payload = $data;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function retrieve_virtual_account(array $data, $requestRef)
    {
        $servicetype = ServiceTypes::RETRIEVE_SINGLE_VIRTUAL_ACCOUNT;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function retrieve_virtual_account_balance(array $data, $requestRef)
    {
        $servicetype = ServiceTypes::RETRIEVE_VIRTUAL_ACCOUNT_BALANCE;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function retrieve_virtual_account_transaction(
        array $data,
        $requestRef
    ) {
        $servicetype = ServiceTypes::ADMIN_VIRTUAL_ACCOUNT_TRANSACTIONS;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function withdraw_virtual_account(array $data, $requestRef)
    {
        $servicetype = ServiceTypes::WITHDRAW_VIRTUAL_ACCOUNT;
        $result = $this->makeRequest($servicetype, $data, $requestRef);

        return $result;
    }

    public function webhook(Request $request)
    {
        // Retrieve the request's body
        $input = @file_get_contents('php://input');

        $event = json_decode($input);

        echo response()->json([$event], 200);

        http_response_code(200);
    }

    public function kudaAccountTransfer($payload, $requestRef = null)
    {
        return $this->makeRequest(
            ServiceTypes::SINGLE_FUND_TRANSFER,
            $payload,
            $requestRef
        );
    }

    private function encryptPassword($password)
    {
        return $this->crypter->encryptRSA($password, $this->publicKey);
    }

    private function encryptPayload(array $payload, $password, $salt)
    {
        return $this->crypter->encryptAES(
            json_encode($payload),
            $password,
            $salt
        );
    }

    private function dencryptPayload(Response $response, $salt)
    {
        $content = $response->getBody()->getContents();
        ['password' => $password, 'data' => $data] = json_decode(
            $content,
            true
        );
        return json_decode(
            $this->crypter->decryptAES(
                $data,
                $this->decryptPassword($password),
                $salt
            )
        );
    }

    private function decryptPassword($password)
    {
        return $this->crypter->decryptRSA($password, $this->privateKey);
    }

    public function makeRequest(
        string $action,
        array $payload,
        $requestRef = null
    ) {
        $client = new Client([
            'base_uri' => $this->baseUri,
        ]);
        $salt = 'randomsalt'; //substr(bin2hex(Random::string(16)),0,16);
        $enctyped_password = $this->encryptPassword(
            $password =
                $this->clientKey .
                '-' .
                substr(bin2hex(Random::string(8)), 0, 5)
        );
        try {
            /**
             * @var Response $response
             */
            $response = $client->post('', [
                'json' => [
                    'data' => $this->encryptPayload(
                        [
                            'serviceType' => $action,
                            'requestRef' =>
                                $requestRef ?? bin2hex(random_bytes(10)),
                            'data' => $payload,
                        ],
                        $password,
                        $salt
                    ),
                ],
                'headers' => ['password' => $enctyped_password],
            ]);

            return $this->dencryptPayload($response, $salt);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            return [
                'Status' => false,
                'Message' => json_decode(
                    $response->getBody()->getContents(),
                    true
                ),
            ];
        } catch (\Throwable $th) {
            return ['Status' => false, 'Message' => $th->getMessage()];
        }
    }

    private function errors($status_code)
    {
        return [
            '400' => 'Exception occured',
            '401' => 'Authentication failure',
            '403' => 'Forbidden',
            '404' => 'Resource not found',
            '405' => 'Method Not Allowed',
            '409' => 'Conflict',
            '412' => 'Precondition Failed',
            '413' => 'Request Entity Too Large',
            '500' => 'Internal Server Error',
            '501' => 'Not Implemented',
            '503' => 'Service Unavailable',
        ][$status_code];
    }
}
