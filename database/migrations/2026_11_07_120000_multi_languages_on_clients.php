<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Several languages per client: captions (picked on "Generate with AI") and review replies (the review's own
     * language among them). Replaces caption_language, gbp_language and gbp_auto_language, keeping what they did.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('caption_languages')->nullable();
            $table->json('gbp_languages')->nullable();
        });

        foreach (DB::table('clients')->get(['id', 'caption_language', 'gbp_language', 'gbp_auto_language']) as $c) {
            $main = $c->gbp_language ?: 'en';
            // Old "Auto language": Devanagari review → Hindi (Marathi for Marathi clients), any other → English
            $reply = $c->gbp_auto_language ? [$main, in_array($main, ['mr', 'mr_en'], true) ? 'mr' : 'hi', 'en'] : [$main];
            DB::table('clients')->where('id', $c->id)->update([
                'caption_languages' => json_encode([$c->caption_language ?: 'en']),
                // list order, as Clients > Edit saves it (with auto on, English comes first = old fallback for no text)
                'gbp_languages' => json_encode(array_values(array_intersect(['en', 'hi', 'hinglish', 'mr', 'mr_en'], $reply))),
            ]);
        }

        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['caption_language', 'gbp_language', 'gbp_auto_language']));
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('caption_language', 10)->default('en');
            $table->string('gbp_language', 10)->default('en');
            $table->boolean('gbp_auto_language')->default(false);
        });
        foreach (DB::table('clients')->get(['id', 'caption_languages', 'gbp_languages']) as $c) {
            $reply = json_decode($c->gbp_languages ?? '[]', true) ?: ['en'];
            DB::table('clients')->where('id', $c->id)->update([
                'caption_language' => (json_decode($c->caption_languages ?? '[]', true) ?: ['en'])[0],
                'gbp_language' => $reply[0],
                'gbp_auto_language' => count($reply) > 1,
            ]);
        }
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['caption_languages', 'gbp_languages']));
    }
};
