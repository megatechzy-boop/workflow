<?php

namespace App\Support;

use App\Models\Content;
use App\Models\GbpReview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
            'City / area' => $client->gbpLocation?->city ?: $client->address,
            'Format' => $item->formatLabel($content->type()),
            'Post date' => $item->post_date?->format('d M Y, l'),
            'Creative copy' => $item->creative_copy,
            'Visual' => $item->visual_brief,
            'Brief' => $item->brief,
            'Remarks' => $item->remarks,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $system = "You write social media captions for an Indian marketing agency. Write in $language."
            .' Length: 8-12 lines and 120-200 words in all. Every line is a full, useful sentence (not 2-4 word fragments), each on its own line (use \n between lines).'
            .' Make it search friendly (Instagram and Facebook search read the caption): the first line is a hook that names the main topic in the words people search for,'
            .' e.g. the service + the problem it solves + the city / area when given. Use 3-5 such keywords naturally across the caption, never stuffed.'
            .' Structure: hook, the problem or need, 2-3 benefits or tips, who it is for, then a clear call to action. At most 3 emojis.'
            .' Do not invent prices, offers, phone numbers or claims that are not in the brief.'
            .($client->is_healthcare ? ' This is a healthcare brand: no medical promises or guarantees.' : '')
            .' Reply with JSON only: {"caption": "...", "hashtags": "#tag1 #tag2 ..."} with 8-15 hashtags: the topic, the niche and the city / area (e.g. #yogaforbackpain #puneyoga), no generic tags like #love or #instagood.'
            // Clients > Edit "Caption style": wins over the defaults above where they differ
            .(trim((string) $client->caption_style) !== '' ? "\n\nThis brand's caption style (follow it over the rules above, but still reply with the JSON):\n".trim($client->caption_style) : '');

        $json = self::ask($system, $brief, 'caption');
        // Small models often still write a few short lines: ask once more, then take what comes
        $lines = self::lines($json['caption']);
        $words = count(preg_split('/\s+/u', $json['caption'], -1, PREG_SPLIT_NO_EMPTY)); // works for Hindi / Marathi too
        if ($lines < self::MIN_LINES || $words < self::MIN_WORDS) {
            $json = self::ask($system, $brief."\n\nYour last caption had only $lines lines and $words words, too short."
                .' Write 8-12 full lines, 120-200 words, with the search keywords this time.', 'caption');
        }

        return ['caption' => $json['caption'], 'hashtags' => trim((string) ($json['hashtags'] ?? ''))];
    }

    /** Google Business Profile post caption: local search words, no hashtags (GBP limit 1500 characters) */
    public static function gbp(Content $content, string $languageKey): string
    {
        $item = $content->task->item;
        $client = $item->calendar->client;
        $language = ReviewTemplates::LANGUAGES[$languageKey] ?? 'English';
        $version = $content->versions()->where('number', $content->current_version)->first();

        $brief = collect([
            'Business' => $client->name,
            'City / area' => $client->gbpLocation?->city ?: $client->address,
            'Creative copy' => $item->creative_copy,
            'Brief' => $item->brief,
            'Social media caption of the same post' => $version?->caption,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $json = self::ask("You write Google Business Profile posts for local businesses in India. Write in $language."
            .' 100-250 words, under 1400 characters, in 3-5 short paragraphs (use \n\n between them).'
            .' It must help local search: the first sentence names the service, the problem it solves and the city / area;'
            .' use 3-5 local keywords naturally (service + city, "near" the area), never stuffed.'
            .' Then the benefits, who it is for, and a clear call to action (visit, call or book). No hashtags, at most 2 emojis.'
            .' Do not invent prices, offers, phone numbers or claims that are not in the brief.'
            .($client->is_healthcare ? ' This is a healthcare business: no medical promises or guarantees, no patient details.' : '')
            .' Reply with JSON only: {"caption": "..."}', $brief, 'caption');

        return Str::limit($json['caption'], 1500, '');
    }

    /** Owner's rule: every post, carousel and reel caption has at least 6 lines, and (search friendly) enough words */
    private const MIN_LINES = 6;

    private const MIN_WORDS = 80;

    private static function lines(string $text): int
    {
        return count(array_filter(preg_split('/\R/u', $text), fn ($l) => trim($l) !== ''));
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
