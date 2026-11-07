<?php

namespace App\Support;

use App\Models\Content;
use App\Models\GbpReview;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** AI drafts (Groq): post captions + hashtags, and Google review replies. The team checks and saves/posts them. */
class CaptionAi
{
    /**
     * @param  string  $languageKey  one of the client's caption languages (picked next to "Generate with AI")
     * @return array{caption: string, hashtags: string}
     */
    public static function generate(Content $content, string $languageKey): array
    {
        $item = $content->task->item;
        $client = $item->calendar->client;
        $language = ReviewTemplates::LANGUAGES[$languageKey] ?? 'English';

        $brief = collect([
            'Brand' => $client->name,
            'Format' => $item->formatLabel($content->type()),
            'Post date' => $item->post_date?->format('d M Y, l'),
            'Creative copy' => $item->creative_copy,
            'Visual' => $item->visual_brief,
            'Brief' => $item->brief,
            'Remarks' => $item->remarks,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $json = self::ask("You write social media captions for an Indian marketing agency. Write in $language."
            .' Keep it short and engaging (2-4 lines, a call to action, at most 2 emojis). Do not invent prices, offers, phone numbers or claims that are not in the brief.'
            .($client->is_healthcare ? ' This is a healthcare brand: no medical promises or guarantees.' : '')
            .' Reply with JSON only: {"caption": "...", "hashtags": "#tag1 #tag2 ..."} with 5-10 relevant hashtags.'
            // Clients > Edit "Caption style": wins over the defaults above where they differ
            .(trim((string) $client->caption_style) !== '' ? "\n\nThis brand's caption style (follow it over the rules above, but still reply with the JSON):\n".trim($client->caption_style) : ''),
            $brief, 'caption');

        return ['caption' => $json['caption'], 'hashtags' => trim((string) ($json['hashtags'] ?? ''))];
    }

    /** Reply to a Google review, in the language the review templates would use (client's Reply language + Auto language) */
    public static function reviewReply(GbpReview $review): string
    {
        $client = $review->client;
        $language = ReviewTemplates::LANGUAGES[ReviewTemplates::languageFor($client, $review->comment)] ?? 'English';
        $name = ReviewTemplates::firstName($client, $review->reviewer_name); // none for anonymous reviewers and healthcare clients

        $about = collect([
            'Business' => $client->name,
            'Business phone' => $client->phone,
            'Reviewer first name' => $name,
            'Stars' => $review->rating.' of 5',
            'Review' => $review->comment ?: '(no text, stars only)',
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $json = self::ask("You reply to Google reviews on behalf of the business owner. Write in $language."
            .' 2-3 short sentences, warm and polite, thank the reviewer and answer what they wrote. '
            .($name ? 'Address the reviewer by the first name given. ' : 'Do not use any name for the reviewer. ')
            .'For 1-3 stars: apologise without arguing or admitting fault, and invite them to contact the business'
            .($client->phone ? ' on the phone number given' : '').'. Never promise refunds, discounts or anything else not in the review.'
            .($client->is_healthcare ? ' This is a healthcare business: never mention any treatment, condition or patient details.' : '')
            .' No hashtags, at most 1 emoji. Reply with JSON only: {"reply": "..."}',
            $about, 'reply');

        return $json['reply'];
    }

    /** One Groq chat call that must return JSON with a non-empty $field */
    private static function ask(string $system, string $user, string $field): array
    {
        $response = Http::withToken(config('services.groq.key'))->timeout(60)->post(config('services.groq.url'), [
            'model' => config('services.groq.model'),
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        ]);
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'HTTP '.$response->status());
        }

        // The model may wrap the JSON in text or a code block: take the outermost {...}
        $text = (string) $response->json('choices.0.message.content');
        $json = json_decode(preg_match('/\{.*\}/s', $text, $m) ? $m[0] : '', true);
        if (! is_string($json[$field] ?? null) || trim($json[$field]) === '') {
            throw new RuntimeException("The AI answer had no $field. Please try again.");
        }
        $json[$field] = trim($json[$field]);

        return $json;
    }
}
