<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolaService
{
    private const GATEWAY_URL = 'https://x1.cardknox.com/gatewayJSON';
    private const API_VERSION = '5.0.0';
    private const SOFTWARE_NAME = 'AutoGo';
    private const SOFTWARE_VERSION = '1.0.0';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.sola.api_key', '');
    }

    public function isConfigured(): bool { return !empty($this->apiKey); }

    public function sale(array $data): array { return $this->processTransaction('cc:sale', $data); }
    public function authOnly(array $data): array { return $this->processTransaction('cc:authonly', $data); }

    public function capture(string $refNum, ?float $amount = null): array
    {
        $params = ['xRefNum' => $refNum];
        if ($amount !== null) $params['xAmount'] = number_format($amount, 2, '.', '');
        return $this->sendRequest('cc:capture', $params);
    }

    public function void(string $refNum): array { return $this->sendRequest('cc:void', ['xRefNum' => $refNum]); }

    public function refund(string $refNum, ?float $amount = null): array
    {
        $params = ['xRefNum' => $refNum];
        if ($amount !== null) $params['xAmount'] = number_format($amount, 2, '.', '');
        return $this->sendRequest('cc:refund', $params);
    }

    public function voidOrRefund(string $refNum): array { return $this->sendRequest('cc:voidrefund', ['xRefNum' => $refNum]); }

    private function processTransaction(string $command, array $data): array
    {
        $params = ['xAmount' => number_format($data['amount'], 2, '.', '')];
        if (!empty($data['token'])) {
            $params['xToken'] = $data['token'];
        } elseif (!empty($data['card_number'])) {
            $params['xCardNum'] = $data['card_number'];
            $params['xExp'] = $data['exp'] ?? '';
            if (!empty($data['cvv'])) $params['xCVV'] = $data['cvv'];
        }
        if (!empty($data['name'])) $params['xName'] = $data['name'];
        if (!empty($data['invoice'])) $params['xInvoice'] = $data['invoice'];
        if (!empty($data['street'])) $params['xStreet'] = $data['street'];
        if (!empty($data['zip'])) $params['xZip'] = $data['zip'];
        if (!empty($data['allow_duplicate'])) $params['xAllowDuplicate'] = 'TRUE';
        return $this->sendRequest($command, $params);
    }

    private function sendRequest(string $command, array $params): array
    {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'Sola API key is not configured'];

        $payload = array_merge([
            'xKey' => $this->apiKey, 'xVersion' => self::API_VERSION,
            'xSoftwareName' => self::SOFTWARE_NAME, 'xSoftwareVersion' => self::SOFTWARE_VERSION,
            'xCommand' => $command,
        ], $params);

        try {
            $response = Http::timeout(30)->post(self::GATEWAY_URL, $payload);
            $result = $response->json() ?? [];
            $success = ($result['xResult'] ?? '') === 'A';

            if (!$success) {
                Log::warning('Sola transaction failed', [
                    'command' => $command, 'result' => $result['xResult'] ?? 'unknown',
                    'error' => $result['xError'] ?? '', 'status' => $result['xStatus'] ?? '',
                ]);
            }

            return [
                'success' => $success, 'result' => $result['xResult'] ?? null,
                'ref_num' => $result['xRefNum'] ?? null, 'auth_code' => $result['xAuthCode'] ?? null,
                'auth_amount' => $result['xAuthAmount'] ?? null, 'masked_card' => $result['xMaskedCardNumber'] ?? null,
                'card_type' => $result['xCardType'] ?? null, 'token' => $result['xToken'] ?? null,
                'error' => $result['xError'] ?? null, 'status' => $result['xStatus'] ?? null,
                'raw_response' => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Sola API exception', ['command' => $command, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
