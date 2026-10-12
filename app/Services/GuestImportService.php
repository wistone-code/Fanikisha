<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a guest list from photos or screenshots (a handwritten or printed list, a WhatsApp chat, a contacts
 * screen) with the same Gemini set-up as the pledge and schedule photo imports. Nothing is saved here: the
 * page puts the result in the import box for the organiser to check before importing.
 */
class GuestImportService
{
    /**
     * @param  UploadedFile[]  $photos
     * @return array{successful: bool, items?: array<int, array{name: string, phone: ?string, card: ?string}>, error?: string}
     */
    public function extract(array $photos): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return ['successful' => false, 'error' => 'Photo import is not configured — missing Gemini API key.'];
        }

        try {
            $imageParts = array_map(fn (UploadedFile $photo) => [
                'inline_data' => [
                    'mime_type' => $photo->getMimeType(),
                    'data' => base64_encode(file_get_contents($photo->getRealPath())),
                ],
            ], $photos);

            $instructions = <<<TEXT
                Extract a guest list from the attached photo(s). The photo may be a handwritten or
                printed list, a screenshot of a WhatsApp chat or a phone's contacts, and may be
                imperfect or at an angle — do your best.

                For each distinct guest, extract:
                - "name": the guest's name exactly as written (string)
                - "phone": their phone number exactly as shown, or null if there is none
                - "card": "double" if the line says double, couple, wawili or "x2" (a card for two people),
                  "single" if it says single or mmoja, otherwise null

                Return a JSON array of these objects in the order they appear. Skip headings, titles,
                dates, totals and any line without a person's name. If there are no guests, return [].
                TEXT;

            $model = 'gemini-3.6-flash';

            $response = Http::timeout(60)->withHeaders(['x-goog-api-key' => $apiKey])->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [[
                        'parts' => [...$imageParts, ['text' => $instructions]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'phone' => ['type' => 'STRING', 'nullable' => true],
                                    'card' => ['type' => 'STRING', 'nullable' => true, 'enum' => ['single', 'double']],
                                ],
                                'required' => ['name'],
                            ],
                        ],
                    ],
                ]
            );

            if (! $response->successful()) {
                Log::warning('Guest photo import: Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);

                return ['successful' => false, 'error' => 'Could not reach the reading service. Try again.'];
            }

            $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
            $items = json_decode($text, true);

            if (! is_array($items)) {
                Log::warning('Guest photo import: unparseable response', ['text' => $text]);

                return ['successful' => false, 'error' => "Couldn't read a guest list from that photo. Try a clearer image."];
            }

            $items = array_values(array_filter(array_map(function ($item) {
                $card = strtolower(trim((string) ($item['card'] ?? '')));

                return [
                    'name' => trim((string) ($item['name'] ?? '')),
                    'phone' => filled($item['phone'] ?? null) ? trim((string) $item['phone']) : null,
                    'card' => in_array($card, ['single', 'double'], true) ? $card : null,
                ];
            }, $items), fn ($item) => $item['name'] !== ''));

            if (empty($items)) {
                return ['successful' => false, 'error' => 'No guests were found in that photo.'];
            }

            return ['successful' => true, 'items' => $items];
        } catch (Throwable $e) {
            Log::error('Guest photo import exception', ['message' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach the reading service. Try again.'];
        }
    }
}
