<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PledgeImportService
{
    /**
     * Extract candidate pledgers (name/phone/amount) from one or more photos
     * of a written or printed pledge list, using Gemini's vision capability —
     * the same approach and free API tier as the Schedule page's photo
     * import (see ScheduleImportService). Nothing is saved here; this only
     * returns candidates for the review screen to confirm or edit.
     *
     * @param  UploadedFile[]  $photos
     * @return array{successful: bool, items?: array<int, array{name: string, phone: ?string, amount: float}>, error?: string}
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
                Extract a list of pledgers/contributors from the attached photo(s) of
                a pledge list, contribution sheet, or guest list. The photo may be
                handwritten, printed, or a screenshot, and may be imperfect or at an
                angle — do your best.

                For each distinct person, extract:
                - "name": the person's full name, as written (string)
                - "phone": their phone number if shown, otherwise null (string or null)
                - "amount": the pledged/contribution amount as a plain number, with no
                  currency symbol or thousands separators (number)

                Return a JSON array of these objects. Skip any row that has no name or
                no amount at all (e.g. a header row). If the image contains no
                identifiable pledgers, return an empty array: []
                TEXT;

            $model = 'gemini-3.6-flash';

            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
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
                                    'amount' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['name', 'amount'],
                            ],
                        ],
                    ],
                ]
            );

            if (! $response->successful()) {
                Log::warning('Pledge photo import: Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['successful' => false, 'error' => 'Could not reach the extraction service. Try again.'];
            }

            $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
            $items = json_decode($text, true);

            if (! is_array($items)) {
                Log::warning('Pledge photo import: unparseable response', ['text' => $text]);

                return ['successful' => false, 'error' => "Couldn't read a pledge list from that photo. Try a clearer image."];
            }

            if (empty($items)) {
                return ['successful' => false, 'error' => 'No pledgers were found in that photo.'];
            }

            $items = array_map(fn ($item) => [
                'name' => (string) ($item['name'] ?? ''),
                'phone' => filled($item['phone'] ?? null) ? (string) $item['phone'] : null,
                'amount' => (float) ($item['amount'] ?? 0),
            ], $items);

            // Drop anything with no name or a non-positive amount — matches the
            // same validation the CSV/text import already enforces.
            $items = array_values(array_filter($items, fn ($item) => $item['name'] !== '' && $item['amount'] > 0));

            if (empty($items)) {
                return ['successful' => false, 'error' => 'No pledgers were found in that photo.'];
            }

            return ['successful' => true, 'items' => $items];
        } catch (Throwable $e) {
            Log::error('Pledge photo import exception', ['message' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach the extraction service. Try again.'];
        }
    }
}
