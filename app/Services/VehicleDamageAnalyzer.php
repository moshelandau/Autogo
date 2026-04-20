<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReservationInspection;
use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VehicleDamageAnalyzer
{
    private Client $client;

    public function __construct()
    {
        $this->client = \Anthropic::client(config('services.anthropic.api_key', ''));
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.anthropic.api_key'));
    }

    /**
     * Analyze a single vehicle photo for damage.
     */
    public function analyzeImage(ReservationInspection $inspection): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Anthropic API key not configured'];
        }

        try {
            $imagePath = Storage::disk('public')->path($inspection->image_path);

            if (!file_exists($imagePath)) {
                return ['success' => false, 'error' => 'Image file not found'];
            }

            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $imageData,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => "You are a vehicle damage inspector. Analyze this photo of a vehicle's {$inspection->area} area.

Respond in JSON format only:
{
  \"has_damage\": true/false,
  \"severity\": \"none\" | \"minor\" | \"moderate\" | \"severe\",
  \"damage_types\": [\"scratch\", \"dent\", \"crack\", \"paint_chip\", \"broken_part\", etc.],
  \"description\": \"Brief description of any damage found\",
  \"confidence\": 0.0 to 1.0
}

If no damage is visible, set has_damage to false with severity 'none' and empty damage_types.
Be thorough but accurate — only flag real damage, not dirt or reflections.",
                            ],
                        ],
                    ],
                ],
            ]);

            $text = $response->content[0]->text ?? '';

            // Extract JSON from response
            $jsonMatch = preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches);
            if ($jsonMatch) {
                $analysis = json_decode($matches[0], true);
            } else {
                $analysis = json_decode($text, true);
            }

            if (!$analysis) {
                return ['success' => false, 'error' => 'Could not parse AI response', 'raw' => $text];
            }

            // Update the inspection record
            $inspection->update([
                'ai_analysis' => $analysis,
                'has_damage' => $analysis['has_damage'] ?? false,
            ]);

            return [
                'success' => true,
                'analysis' => $analysis,
            ];
        } catch (\Throwable $e) {
            Log::error('Vehicle damage analysis failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Compare pickup vs return photos for the same area to detect NEW damage.
     */
    public function compareImages(ReservationInspection $pickupImage, ReservationInspection $returnImage): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Anthropic API key not configured'];
        }

        try {
            $pickupPath = Storage::disk('public')->path($pickupImage->image_path);
            $returnPath = Storage::disk('public')->path($returnImage->image_path);

            if (!file_exists($pickupPath) || !file_exists($returnPath)) {
                return ['success' => false, 'error' => 'Image files not found'];
            }

            $pickupData = base64_encode(file_get_contents($pickupPath));
            $returnData = base64_encode(file_get_contents($returnPath));
            $pickupMime = mime_content_type($pickupPath) ?: 'image/jpeg';
            $returnMime = mime_content_type($returnPath) ?: 'image/jpeg';

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Compare these two photos of a vehicle's {$pickupImage->area} area. The FIRST image is from PICKUP (before rental). The SECOND image is from RETURN (after rental).",
                            ],
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $pickupMime,
                                    'data' => $pickupData,
                                ],
                            ],
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $returnMime,
                                    'data' => $returnData,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => "Identify any NEW damage that appeared between pickup and return.

Respond in JSON format only:
{
  \"new_damage_detected\": true/false,
  \"severity\": \"none\" | \"minor\" | \"moderate\" | \"severe\",
  \"new_damage_types\": [\"scratch\", \"dent\", \"crack\", etc.],
  \"description\": \"Description of new damage found\",
  \"pre_existing_damage\": \"Description of any damage visible in pickup photo\",
  \"confidence\": 0.0 to 1.0,
  \"charge_recommended\": true/false,
  \"estimated_repair_cost_range\": \"$X - $Y\" or null
}

Only flag damage as NEW if it's clearly not present in the pickup photo.",
                            ],
                        ],
                    ],
                ],
            ]);

            $text = $response->content[0]->text ?? '';

            $jsonMatch = preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches);
            if ($jsonMatch) {
                $analysis = json_decode($matches[0], true);
            } else {
                $analysis = json_decode($text, true);
            }

            if (!$analysis) {
                return ['success' => false, 'error' => 'Could not parse AI response', 'raw' => $text];
            }

            // Update the return inspection with comparison results
            $returnImage->update([
                'ai_analysis' => $analysis,
                'has_damage' => $analysis['new_damage_detected'] ?? false,
            ]);

            return [
                'success' => true,
                'analysis' => $analysis,
            ];
        } catch (\Throwable $e) {
            Log::error('Vehicle damage comparison failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyze ALL return photos for a reservation, comparing with pickup photos.
     */
    public function analyzeReservation(int $reservationId): array
    {
        $pickupImages = ReservationInspection::where('reservation_id', $reservationId)
            ->where('type', 'pickup')
            ->get()
            ->keyBy('area');

        $returnImages = ReservationInspection::where('reservation_id', $reservationId)
            ->where('type', 'return')
            ->get()
            ->keyBy('area');

        $results = [];
        $newDamageFound = false;

        foreach (ReservationInspection::REQUIRED_AREAS as $area) {
            $pickup = $pickupImages->get($area);
            $return = $returnImages->get($area);

            if ($pickup && $return) {
                // Compare pickup vs return
                $result = $this->compareImages($pickup, $return);
                $results[$area] = $result;

                if ($result['success'] && ($result['analysis']['new_damage_detected'] ?? false)) {
                    $newDamageFound = true;
                }
            } elseif ($return) {
                // Only return image — analyze standalone
                $result = $this->analyzeImage($return);
                $results[$area] = $result;

                if ($result['success'] && ($result['analysis']['has_damage'] ?? false)) {
                    $newDamageFound = true;
                }
            }
        }

        return [
            'new_damage_found' => $newDamageFound,
            'areas_analyzed' => count($results),
            'results' => $results,
        ];
    }
}
