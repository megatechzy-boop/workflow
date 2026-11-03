<?php

namespace App\Support;

use App\Models\Client;
use App\Models\GbpReview;
use App\Models\ReviewTemplate;
use Illuminate\Support\Facades\DB;

/** Review reply templates: default set, language pick, {placeholders} and rotation (no AI) */
class ReviewTemplates
{
    public const LANGUAGES = ['en' => 'English', 'hi' => 'Hindi', 'hinglish' => 'Hinglish', 'mr' => 'Marathi', 'mr_en' => 'Marathi (English letters)'];

    public const GROUPS = ['positive' => 'Positive (4–5 stars, auto reply)', 'neutral' => 'Neutral (3 stars, draft for approval)'];

    public const PLACEHOLDERS = ['{greeting}', '{name}', '{business}', '{phone}', '{city}'];

    /** {greeting}: [with a name ("%s" = first name), without a name] */
    public const GREETINGS = [
        'en' => ['Hi %s,', 'Hi there,'],
        'hi' => ['%s जी,', 'नमस्ते,'],
        'hinglish' => ['%s ji,', 'Namaste,'],
        'mr' => ['%s जी,', 'नमस्कार,'],
        'mr_en' => ['%s ji,', 'Namaskar,'],
    ];

    /** Copied to every new client; Admin can change a client's copy (or the default set) in Admin > Review templates */
    public const DEFAULTS = [
        'positive' => [
            'en' => [
                "{greeting} thank you so much for the lovely review! We're glad you had a great experience at {business}. Hope to see you again soon.",
                "{greeting} thank you for your kind words! It means a lot to the whole {business} team.",
                "{greeting} we really appreciate you taking the time to share your feedback. Thank you for choosing {business}!",
                "{greeting} thank you for the wonderful review! Your support keeps us going.",
                "{greeting} thanks a lot for the great rating and review! We look forward to seeing you again.",
            ],
            'hi' => [
                '{greeting} इतने अच्छे रिव्यू के लिए दिल से धन्यवाद! हमें खुशी है कि {business} में आपका अनुभव अच्छा रहा। फिर मिलेंगे!',
                '{greeting} आपके प्यार भरे शब्दों के लिए बहुत-बहुत शुक्रिया। यह हमारी पूरी टीम के लिए बहुत मायने रखता है।',
                '{greeting} अपना कीमती समय निकालकर रिव्यू देने के लिए धन्यवाद। {business} को चुनने के लिए आभार!',
                '{greeting} आपके शानदार रिव्यू के लिए धन्यवाद! आपका साथ हमें और बेहतर करने की प्रेरणा देता है।',
                '{greeting} बढ़िया रेटिंग और रिव्यू के लिए धन्यवाद! आपसे दोबारा मिलने का इंतज़ार रहेगा।',
            ],
            'hinglish' => [
                '{greeting} itne pyaare review ke liye dil se thank you! Khushi hai ki {business} mein aapka experience accha raha. Phir milenge!',
                '{greeting} aapke kind words ke liye bahut bahut shukriya. Ye hamari poori team ke liye bahut matter karta hai.',
                '{greeting} time nikaal ke review dene ke liye thank you. {business} ko choose karne ke liye dhanyavaad!',
                '{greeting} itne shaandaar review ke liye thanks! Aapka support hume aur better karne ki motivation deta hai.',
                '{greeting} badhiya rating aur review ke liye thank you! Aapse dobara milne ka intezaar rahega.',
            ],
            'mr' => [
                '{greeting} इतक्या छान रिव्ह्यूबद्दल मनापासून धन्यवाद! {business} मध्ये तुमचा अनुभव चांगला होता याचा आम्हाला आनंद आहे. पुन्हा भेटूया!',
                '{greeting} तुमच्या प्रेमळ शब्दांबद्दल खूप खूप आभार. आमच्या संपूर्ण टीमसाठी हे खूप महत्त्वाचे आहे.',
                '{greeting} वेळ काढून रिव्ह्यू दिल्याबद्दल धन्यवाद. {business} ची निवड केल्याबद्दल आभार!',
                '{greeting} या सुंदर रिव्ह्यूबद्दल धन्यवाद! तुमची साथ आम्हाला अजून चांगलं काम करण्याची प्रेरणा देते.',
                '{greeting} छान रेटिंग आणि रिव्ह्यूबद्दल धन्यवाद! तुम्हाला पुन्हा भेटण्याची वाट पाहतो.',
            ],
            'mr_en' => [
                '{greeting} itkya chhan review baddal manapasun dhanyavaad! {business} madhe tumcha anubhav changla hota yacha amhala anand aahe. Punha bhetuya!',
                '{greeting} tumchya premal shabdanbaddal khup khup aabhar. Amchya sampurna team sathi he khup mahatvacha aahe.',
                '{greeting} vel kadhun review dilyabaddal dhanyavaad. {business} chi nivad kelyabaddal aabhar!',
                '{greeting} ya sundar review baddal thank you! Tumchi saath amhala ajun changla kaam karaychi prerana dete.',
                '{greeting} chhan rating ani review baddal dhanyavaad! Tumhala punha bhetaychi vaat pahto.',
            ],
        ],
        'neutral' => [
            'en' => [
                "{greeting} thank you for your feedback. We'd love to know how we can make your next experience at {business} even better.",
                "{greeting} thanks for taking the time to rate us. We're always working to improve, and your feedback helps.",
                "{greeting} thank you for the review. We'd like to hear more about what we can do better, so please call us on {phone}.",
                "{greeting} we appreciate your honest feedback. The {business} team will work on it, and we hope to give you a 5-star experience next time.",
                '{greeting} thank you for sharing your experience. Your suggestions matter to us, and we hope to see you again.',
            ],
            'hi' => [
                '{greeting} आपकी राय के लिए धन्यवाद। हम जानना चाहेंगे कि अगली बार {business} में आपका अनुभव और बेहतर कैसे बना सकते हैं।',
                '{greeting} रेटिंग देने के लिए शुक्रिया। हम लगातार सुधार कर रहे हैं और आपका फ़ीडबैक इसमें हमारी मदद करता है।',
                '{greeting} रिव्यू के लिए धन्यवाद। अपने सुझाव बताने के लिए कृपया हमें {phone} पर कॉल करें।',
                '{greeting} आपकी सच्ची राय के लिए आभार। {business} की टीम इस पर ज़रूर काम करेगी।',
                '{greeting} अपना अनुभव बताने के लिए धन्यवाद। आपके सुझाव हमारे लिए ज़रूरी हैं, उम्मीद है आप फिर आएंगे।',
            ],
            'hinglish' => [
                '{greeting} feedback ke liye thank you. Hum jaanna chahenge ki next time {business} mein aapka experience aur better kaise bana sakte hain.',
                '{greeting} rating dene ke liye shukriya. Hum lagatar improve kar rahe hain aur aapka feedback isme madad karta hai.',
                '{greeting} review ke liye thank you. Apne suggestions batane ke liye please hume {phone} par call karein.',
                '{greeting} aapke honest feedback ke liye thanks. {business} ki team is par zaroor kaam karegi.',
                '{greeting} apna experience share karne ke liye thank you. Aapke suggestions hamare liye important hain, umeed hai aap phir aayenge.',
            ],
            'mr' => [
                '{greeting} तुमच्या अभिप्रायाबद्दल धन्यवाद. पुढच्या वेळी {business} मध्ये तुमचा अनुभव अजून चांगला कसा करता येईल, हे जाणून घ्यायला आवडेल.',
                '{greeting} रेटिंग दिल्याबद्दल आभार. आम्ही सतत सुधारणा करत आहोत आणि तुमचा अभिप्राय त्यात मदत करतो.',
                '{greeting} रिव्ह्यूबद्दल धन्यवाद. तुमच्या सूचना सांगण्यासाठी कृपया आम्हाला {phone} वर कॉल करा.',
                '{greeting} तुमच्या प्रामाणिक अभिप्रायाबद्दल आभार. {business} ची टीम यावर नक्की काम करेल.',
                '{greeting} तुमचा अनुभव सांगितल्याबद्दल धन्यवाद. तुमच्या सूचना आमच्यासाठी महत्त्वाच्या आहेत, तुम्ही पुन्हा याल अशी आशा आहे.',
            ],
            'mr_en' => [
                '{greeting} tumchya feedback baddal dhanyavaad. Pudhchya veli {business} madhe tumcha anubhav ajun changla kasa karta yeil, he janun ghyayla aavdel.',
                '{greeting} rating dilyabaddal aabhar. Amhi satat sudharna karat aahot ani tumcha feedback tyat madat karto.',
                '{greeting} review baddal dhanyavaad. Tumchya suchana sangnyasathi krupaya amhala {phone} var call kara.',
                '{greeting} tumchya pramanik feedback baddal aabhar. {business} chi team yavar nakki kaam karel.',
                '{greeting} tumcha anubhav sangitlyabaddal dhanyavaad. Tumchya suchana amchyasathi mahatvachya aahet, tumhi punha yaal ashi asha aahe.',
            ],
        ],
    ];

    /** Default set rows (client_id null) for the migration */
    public static function defaultRows(): array
    {
        $rows = [];
        foreach (self::DEFAULTS as $group => $languages) {
            foreach ($languages as $language => $bodies) {
                foreach ($bodies as $body) {
                    $rows[] = ['client_id' => null, 'rating_group' => $group, 'language' => $language, 'body' => $body, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        return $rows;
    }

    /** A new client gets its own copy of the default set (one insert) */
    public static function copyDefaultsTo(Client $client): void
    {
        $rows = ReviewTemplate::whereNull('client_id')->get(['rating_group', 'language', 'body', 'is_active'])
            ->map(fn ($t) => $t->toArray() + ['client_id' => $client->id, 'created_at' => now(), 'updated_at' => now()])->all();
        if ($rows) {
            DB::table('review_templates')->insert($rows);
        }
    }

    /** Auto-language: a review written in Devanagari gets the client's Hindi / Marathi, any other gets English */
    public static function languageFor(Client $client, ?string $text): string
    {
        $default = $client->gbp_language ?: 'en';
        if (! $client->gbp_auto_language) {
            return $default;
        }
        if (preg_match('/\p{Devanagari}/u', (string) $text)) {
            return in_array($default, ['mr', 'mr_en'], true) ? 'mr' : 'hi';
        }

        return 'en';
    }

    /** First name for {name}/{greeting}; none for anonymous reviewers and healthcare clients (no patient details) */
    public static function firstName(Client $client, ?string $reviewer): ?string
    {
        $reviewer = trim((string) $reviewer);
        if ($client->is_healthcare || $reviewer === '' || stripos($reviewer, 'google user') !== false) {
            return null;
        }

        return mb_convert_case(preg_split('/\s+/u', $reviewer)[0], MB_CASE_TITLE);
    }

    /** @param  array{name: ?string, business: ?string, phone: ?string, city: ?string}  $values */
    public static function render(string $body, string $language, array $values): string
    {
        [$withName, $noName] = self::GREETINGS[$language] ?? self::GREETINGS['en'];
        $name = $values['name'] ?? null;

        return trim(strtr($body, [
            '{greeting}' => $name ? sprintf($withName, $name) : $noName,
            '{name}' => (string) $name,
            '{business}' => (string) ($values['business'] ?? ''),
            '{phone}' => (string) ($values['phone'] ?? ''),
            '{city}' => (string) ($values['city'] ?? ''),
        ]));
    }

    /**
     * Template + text for a review, or null when the client has none for that group / language.
     * Skips templates whose {business}/{phone}/{city} has no value, and the last 3 used on this location.
     *
     * @return array{template: ReviewTemplate, text: string}|null
     */
    public static function pick(GbpReview $review, string $group): ?array
    {
        $client = $review->client;
        $location = $review->location;
        $values = ['name' => self::firstName($client, $review->reviewer_name), 'business' => $location->title ?: $client->name,
            'phone' => $location->phone, 'city' => $location->city];
        $language = self::languageFor($client, $review->comment);

        $templates = ReviewTemplate::where('client_id', $client->id)->where('rating_group', $group)->where('is_active', true)
            ->whereIn('language', array_unique([$language, 'en']))->get();
        $templates = $templates->where('language', $language)->isNotEmpty() ? $templates->where('language', $language) : $templates;
        $usable = $templates->filter(function (ReviewTemplate $t) use ($values) {
            foreach (['business', 'phone', 'city'] as $key) {
                if (str_contains($t->body, '{'.$key.'}') && blank($values[$key])) {
                    return false;
                }
            }

            return true;
        });
        if ($usable->isEmpty()) {
            return null;
        }

        $recent = GbpReview::where('gbp_location_id', $location->id)->whereNotNull('template_id')->where('id', '!=', $review->id)
            ->latest('templated_at')->latest('id')->limit(3)->pluck('template_id')->all();
        $fresh = $usable->whereNotIn('id', $recent);
        $template = ($fresh->isNotEmpty() ? $fresh : $usable->whereNotIn('id', array_slice($recent, 0, 1))->whenEmpty(fn () => $usable))->random();

        return ['template' => $template, 'text' => self::render($template->body, $template->language, $values)];
    }
}
