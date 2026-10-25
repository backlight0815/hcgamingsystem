<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_profiles', function (Blueprint $table): void {
            $table->string('job_position', 80)->nullable()->after('job_title')->index();
            $table->string('phone', 40)->nullable()->after('job_position');
            $table->string('photo_path')->nullable()->after('phone');
        });

        // Only explicit, unambiguous legacy titles are backfilled. Unknown titles stay
        // unassigned so an administrator can classify them without granting privileges.
        foreach (['developer' => 'Developer', 'software developer' => 'Developer',
            'qa' => 'QA Engineer', 'qa engineer' => 'QA Engineer',
            'quality assurance engineer' => 'QA Engineer', 'business analyst' => 'Business Analyst',
            'project manager' => 'Project Manager', 'product owner' => 'Product Owner',
        ] as $title => $position) {
            DB::table('pms_profiles')->whereRaw('LOWER(TRIM(job_title)) = ?', [$title])->update(['job_position' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('pms_profiles', fn (Blueprint $table) => $table->dropColumn(['job_position', 'phone', 'photo_path']));
    }
};
