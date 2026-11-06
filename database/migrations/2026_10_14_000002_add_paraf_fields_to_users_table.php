    <?php

    use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('opd_id')->nullable()->after('id')->constrained('opds')->nullOnDelete();
            $table->string('nip', 18)->nullable()->unique()->after('name');
            $table->string('position')->nullable()->after('email');
            $table->string('phone_number', 20)->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('phone_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['opd_id']);
            $table->dropColumn(['opd_id', 'nip', 'position', 'phone_number', 'is_active']);
        });
    }
};
