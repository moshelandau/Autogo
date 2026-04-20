<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditPull;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Credit700Service
{
    public function isConfigured(): bool
    {
        return !empty(config('services.credit700.api_key'));
    }

    /**
     * Pull soft credit (no SSN required, no impact on score).
     */
    public function softPull(CreditPull $pull): array
    {
        if (!$this->isConfigured()) {
            return $this->mockResponse($pull, 'soft');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.credit700.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.credit700.api_url') . '/soft-pull', [
                'first_name' => $pull->first_name,
                'last_name' => $pull->last_name,
                'date_of_birth' => $pull->date_of_birth?->format('Y-m-d'),
                'address' => $pull->address,
                'city' => $pull->city,
                'state' => $pull->state,
                'zip' => $pull->zip,
            ]);

            if (!$response->successful()) {
                Log::warning('700Credit soft pull failed', ['status' => $response->status(), 'body' => $response->body()]);
                $pull->update(['status' => 'failed']);
                return ['success' => false, 'error' => $response->body()];
            }

            $data = $response->json();
            $score = $data['credit_score'] ?? null;

            $pull->update([
                'status' => 'completed',
                'credit_score' => $score,
                'credit_score_model' => $data['score_model'] ?? 'FICO 8',
                'credit_tier' => CreditPull::tierFromScore($score),
                'bureau' => $data['bureau'] ?? 'experian',
                'full_report' => $data,
                'expires_at' => now()->addDays(30),
            ]);

            return ['success' => true, 'pull' => $pull->fresh(), 'score' => $score];
        } catch (\Throwable $e) {
            Log::error('700Credit soft pull exception', ['error' => $e->getMessage()]);
            $pull->update(['status' => 'failed']);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * NOTE: AutoGo only performs SOFT pulls. Hard pulls are done by the
     * dealer / lender after the application is submitted, NOT by AutoGo.
     * The hardPull() method has been intentionally removed.
     */

    /**
     * Mock response when 700Credit API not configured.
     * Returns a random plausible score so UI can be tested.
     */
    private function mockResponse(CreditPull $pull, string $type = 'soft'): array
    {
        $score = random_int(580, 820);

        $pull->update([
            'status' => 'completed',
            'credit_score' => $score,
            'credit_score_model' => 'FICO 8 (MOCK)',
            'credit_tier' => CreditPull::tierFromScore($score),
            'bureau' => 'experian',
            'full_report' => [
                'mock' => true,
                'message' => 'This is a mock response. Configure CREDIT700_API_KEY in .env to use real 700Credit.',
                'credit_score' => $score,
            ],
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'success' => true,
            'mock' => true,
            'pull' => $pull->fresh(),
            'score' => $score,
            'message' => 'Mock 700Credit response (API not configured).',
        ];
    }
}
