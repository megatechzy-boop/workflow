<?php

namespace App\Support;

use App\Models\Content;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** "Generate with AI" on a post: caption + hashtags from the calendar row, in the client's caption language (Groq) */
class CaptionAi
{
    /** @return array{caption: string, hashtags: string} */
    public static function generate(Content $content): array
    {
        $item = $content->task->item;
        $client = $item->calendar->client;
        $language = ReviewTemplates::LANGUAGES[$client->caption_language] ?? 'English';

        $brief = collect([
            'Brand' => $client->name,
            'Format' => $item->formatLabel($content->type()),
            'Post date' => $item->post_date?->format('d M Y, l'),
            'Creative copy' => $item->creative_copy,
            'Visual' => $item->visual_brief,
            'Brief' => $item->brief,
            'Remarks' => $item->remarks,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $response = Http::withToken(config('services.groq.key'))->timeout(60)->post(config('services.groq.url'), [
            'model' => config('services.groq.model'),
            'messages' => [
                ['role' => 'system', 'content' => "You write social media captions for an Indian marketing agency. Write in $language."
                    .' Keep it short and engaging (2-4 lines, a call to action, at most 2 emojis). Do not invent prices, offers, phone numbers or claims that are not in the brief.'
                    .($client->is_healthcare ? ' This is a healthcare brand: no medical promises or guarantees.' : '')
                    .' Reply with JSON only: {"caption": "...", "hashtags": "#tag1 #tag2 ..."} with 5-10 relevant hashtags.'
                    // Clients > Edit "Caption style": wins over the defaults above where they differ
                    .(trim((string) $client->caption_style) !== '' ? "\n\nThis brand's caption style (follow it over the rules above, but still reply with the JSON):\n".trim($client->caption_style) : '')],
                ['role' => 'user', 'content' => $brief],
            ],
        ]);
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'HTTP '.$response->status());
        }

        // The model may wrap the JSON in text or a code block: take the outermost {...}
        $text = (string) $response->json('choices.0.message.content');
        $json = json_decode(preg_match('/\{.*\}/s', $text, $m) ? $m[0] : '', true);
        if (! is_string($json['caption'] ?? null) || trim($json['caption']) === '') {
            throw new RuntimeException('The AI reply had no caption. Please try again.');
        }

        return ['caption' => trim($json['caption']), 'hashtags' => trim((string) ($json['hashtags'] ?? ''))];
    }
}
