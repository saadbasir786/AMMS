<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amms_sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });
        $requestMax = DB::table('meeting_requests')->pluck('request_number')->filter()->map(fn ($n) => (int) preg_replace('/^.*-(\\d+)$/', '$1', $n))->max() ?: 0;
        $meetingMax = DB::table('meetings')->pluck('meeting_number')->filter()->map(fn ($n) => (int) preg_replace('/^.*-(\\d+)$/', '$1', $n))->max() ?: 0;
        DB::table('amms_sequences')->insert([['name'=>'meeting_requests','value'=>$requestMax,'created_at'=>now(),'updated_at'=>now()],['name'=>'meetings','value'=>$meetingMax,'created_at'=>now(),'updated_at'=>now()]]);
    }

    public function down(): void
    {
        Schema::dropIfExists('amms_sequences');
    }
};
