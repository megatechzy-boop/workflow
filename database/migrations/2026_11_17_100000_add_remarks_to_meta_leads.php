<?php

use App\Models\MetaLead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_leads', function (Blueprint $table) {
            $table->string('phone_key', 15)->nullable()->index(); // last 10 digits, for the Repeat tag
            $table->boolean('is_repeat')->default(false);
            $table->date('follow_up_on')->nullable()->index();
        });

        Schema::create('meta_lead_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_lead_id')->constrained('meta_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remark');
            $table->date('follow_up_on')->nullable();
            $table->timestamps();
        });

        // Fill phone_key / is_repeat for leads already synced (oldest first)
        $seen = [];
        foreach (MetaLead::orderBy('lead_at')->orderBy('id')->get() as $lead) {
            $key = MetaLead::phoneKey($lead->phone);
            $repeat = $key && isset($seen[$lead->client_id.'|'.$key]);
            if ($key) {
                $seen[$lead->client_id.'|'.$key] = true;
            }
            MetaLead::whereKey($lead->id)->update(['phone_key' => $key, 'is_repeat' => $repeat]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_lead_remarks');
        Schema::table('meta_leads', function (Blueprint $table) {
            $table->dropIndex(['phone_key']);
            $table->dropIndex(['follow_up_on']);
            $table->dropColumn(['phone_key', 'is_repeat', 'follow_up_on']);
        });
    }
};
