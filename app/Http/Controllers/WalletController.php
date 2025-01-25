<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    protected $soapUrl;

    public function __construct()
    {
        $this->soapUrl = env('SOAP_URL', 'http://localhost:8000/api/soap/server');
    }

    private function callSoapService($action, $data)
    {
        $xmlData = $this->createSoapRequest($action, $data);

        // Imprimir el XML para depuración
        // Log::info("Solicitud XML al servicio SOAP: $xmlData");

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml',
        ])->send('POST', $this->soapUrl, [
            'body' => $xmlData,
        ]);

        // Imprimir la respuesta para depuración
        $responseBody = $response->body();
        // Log::info("Respuesta del servicio SOAP: $responseBody");

        // Comprobar si la respuesta es un XML válido
        if (!str_starts_with(trim($responseBody), '<')) {
            return response()->json([
                'success' => false,
                'cod_error' => '03',
                'message_error' => 'Respuesta no válida del servicio SOAP',
                'data' => null,
            ]);
        }

        $responseArray = $this->parseSoapResponse($responseBody, $action);

        return response()->json($responseArray);
    }

    private function createSoapRequest($action, $data)
    {
        // Crear la solicitud SOAP
        $xml = new \SimpleXMLElement('<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"/>');
        $body = $xml->addChild('soapenv:Body');
        $actionElement = $body->addChild("wal:$action", null, "http://wallet.example.com/");

        foreach ($data as $key => $value) {
            $actionElement->addChild($key, $value);
        }

        return $xml->asXML();
    }

    private function parseSoapResponse($response, $action)
    {
        // Parsear la respuesta SOAP utilizando DOM
        $dom = new \DOMDocument();
        $dom->loadXML($response);

        $data = [];
        $xpath = new \DOMXPath($dom);
        $items = $xpath->query('//return/item');

        foreach ($items as $item) {
            $key = $xpath->query('key', $item)->item(0)->nodeValue;
            $value = $xpath->query('value', $item)->item(0)->nodeValue;

            // Formatear el nodo "data" correctamente según la acción
            if ($key === 'data') {
                if (in_array($action, ['rechargeWallet', 'pay', 'confirmPayment', 'getBalance'])) {
                    $data[$key] = [];
                    foreach ($xpath->query('value/item', $item) as $subItem) {
                        $subKey = $xpath->query('key', $subItem)->item(0)->nodeValue;
                        $subValue = $xpath->query('value', $subItem)->item(0)->nodeValue;
                        $data[$key][$subKey] = $subValue;
                    }
                } elseif ($action === 'registerCustomer') {
                    $data[$key] = [];
                    foreach ($xpath->query('value/*', $item) as $subItem) {
                        $data[$key][$subItem->nodeName] = $subItem->nodeValue;
                    }
                } else {
                    $data[$key] = $value;
                }
            } else {
                $data[$key] = $value;
            }
        }

        return [
            'success' => $data['success'] === 'true',
            'cod_error' => $data['cod_error'],
            'message_error' => $data['message_error'],
            'data' => $data['data'] ?? null,
        ];
    }

    public function registerCustomer(Request $request)
    {
        $data = [
            'document' => $request->input('document'),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ];

        return $this->callSoapService('registerCustomer', $data);
    }

    public function rechargeWallet(Request $request)
    {
        $data = [
            'document' => $request->input('document'),
            'phone' => $request->input('phone'),
            'amount' => $request->input('amount'),
        ];

        return $this->callSoapService('rechargeWallet', $data);
    }

    public function pay(Request $request)
    {
        $data = [
            'document' => $request->input('document'),
            'amount' => $request->input('amount'),
        ];

        return $this->callSoapService('pay', $data);
    }

    public function confirmPayment(Request $request)
    {
        $data = [
            'sessionId' => $request->input('sessionId'),
            'token' => $request->input('token'),
        ];

        return $this->callSoapService('confirmPayment', $data);
    }

    public function getBalance(Request $request)
    {
        $data = [
            'document' => $request->input('document'),
            'phone' => $request->input('phone'),
        ];

        return $this->callSoapService('getBalance', $data);
    }
}
