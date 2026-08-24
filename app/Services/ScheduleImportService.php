<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduleImportService
{
    /**
     * Extract candidate schedule items (title/date/time) from one or more
     * photos of a schedule — handwritten, printed, or a screenshot — using
     * Google Gemini's vision capability. Nothing is saved here; this only
     * returns candidates for the review screen to confirm or edit.
     *
     * Uses Gemini rather than Claude specifically because Gemini has a
     * genuinely free, ongoing API tier (no credit card, no expiration) —
     * see config('services.gemini.api_key').
     *
     * @param  UploadedFile[]  $photos
     * @return array{successful: bool, items?: array<int, array{title: string, date: string, time: ?string}>, error?: string}
     */
    public function extract(array $photos, Event $event): array
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

            // The event's own date grounds entries that show a time but no
            // explicit date (very common on a printed run-of-show), so the
            // model doesn't have to guess a year out of thin air.
            $eventDate = $event->event_date?->toDateString() ?? now()->toDateString();

            $instructions = <<<TEXT
                Extract a ceremony/event schedule from the attached photo(s). The
                photo may be handwritten, printed, or a screenshot, and may be
                imperfect or at an angle — do your best.

                For each distinct schedule entry, extract:
                - "title": the name/description of that agenda item, as written (string)
                - "date": the date in YYYY-MM-DD format if shown or clearly implied. If no
                  date appears anywhere in the photo, use this event's date: {$eventDate}
                - "time": the time in 24-hour HH:MM format if shown, otherwise null

                Return a JSON array of these objects. If the image contains no
                identifiable schedule items, return an empty array: []
                TEXT;

            $model = 'gemini-3.6-flash';

            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [[
                        'parts' => [...$imageParts, ['text' => $instructions]],
                    ]],
                    // Gemini's native JSON mode — the response is guaranteed to be
                    // parseable JSON matching this shape, rather than relying on
                    // the model to follow a "respond with only JSON" instruction.
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'title' => ['type' => 'STRING'],
                                    'date' => ['type' => 'STRING'],
                                    'time' => ['type' => 'STRING', 'nullable' => true],
                                ],
                                'required' => ['title', 'date'],
                            ],
                        ],
                    ],
                ]
            );

            if (! $response->successful()) {
                Log::warning('Schedule photo import: Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['successful' => false, 'error' => 'Could not reach the extraction service. Try again.'];
            }

            $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
            $items = json_decode($text, true);

            if (! is_array($items)) {
                Log::warning('Schedule photo import: unparseable response', ['text' => $text]);

                return ['successful' => false, 'error' => "Couldn't read a schedule from that photo. Try a clearer image."];
            }

            if (empty($items)) {
                return ['successful' => false, 'error' => 'No schedule items were found in that photo.'];
            }

            $items = array_map(fn ($item) => [
                'title' => (string) ($item['title'] ?? ''),
                'date' => filled($item['date'] ?? null) ? (string) $item['date'] : $eventDate,
                'time' => filled($item['time'] ?? null) ? (string) $item['time'] : null,
            ], $items);

            // Drop anything the model returned with no title at all — not a
            // real schedule entry, and would fail the same validation the
            // normal "Add item" form already enforces.
            $items = array_values(array_filter($items, fn ($item) => $item['title'] !== ''));

            if (empty($items)) {
                return ['successful' => false, 'error' => 'No schedule items were found in that photo.'];
            }

            return ['successful' => true, 'items' => $items];
        } catch (Throwable $e) {
            Log::error('Schedule photo import exception', ['message' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach the extraction service. Try again.'];
        }
    }
}
