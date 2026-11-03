<?php

use App\Support\ReviewTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Google reviews, reply templates (default set + a copy per client) and the log of replies sent */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('gbp_auto_reply')->default(false);
            $table->string('gbp_language', 10)->default('en');
            $table->boolean('gbp_auto_language')->default(false);
            $table->boolean('is_healthcare')->default(false); // replies never name the reviewer
        });

        Schema::table('gbp_locations', function (Blueprint $table) {
            $table->timestamp('reviews_synced_at')->nullable(); // first sync only imports (no auto replies / alerts for old reviews)
        });

        Schema::create('review_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete(); // null = default set
            $table->string('rating_group', 10); // positive | neutral
            $table->string('language', 10); // en | hi | hinglish | mr | mr_en
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['client_id', 'rating_group', 'language']);
        });

        Schema::create('gbp_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gbp_location_id')->constrained()->cascadeOnDelete();
            $table->string('review_id', 120)->unique();
            $table->string('review_name'); // accounts/../locations/../reviews/.. (reply endpoint)
            $table->string('reviewer_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status', 12)->default('pending'); // pending | scheduled | draft | replied
            $table->text('draft')->nullable(); // template text waiting (auto reply or approval)
            $table->foreignId('template_id')->nullable()->constrained('review_templates')->nullOnDelete();
            $table->timestamp('templated_at')->nullable();
            $table->timestamp('reply_due_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'reply_due_at']);
        });

        Schema::create('review_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbp_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('review_templates')->nullOnDelete();
            $table->text('final_text');
            $table->timestamp('sent_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // null = auto reply
            $table->timestamps();
        });

        DB::table('review_templates')->insert(ReviewTemplates::defaultRows());
        $defaults = DB::table('review_templates')->whereNull('client_id')->get(['rating_group', 'language', 'body']);
        foreach (DB::table('clients')->pluck('id') as $clientId) {
            DB::table('review_templates')->insert($defaults->map(fn ($t) => (array) $t + ['client_id' => $clientId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reply_logs');
        Schema::dropIfExists('gbp_reviews');
        Schema::dropIfExists('review_templates');
        Schema::table('gbp_locations', fn (Blueprint $table) => $table->dropColumn('reviews_synced_at'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['gbp_auto_reply', 'gbp_language', 'gbp_auto_language', 'is_healthcare']));
    }
};
