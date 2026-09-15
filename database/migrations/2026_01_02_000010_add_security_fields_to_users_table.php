<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 3 — champs de sécurité des comptes.
 *
 *  - is_admin              : accès en plus à Administration + Paramètres (3.1) ;
 *  - locked_at             : compte verrouillé (bloqué sans être supprimé) —
 *                            aucun accès, y compris sessions déjà ouvertes (3.1) ;
 *  - must_change_password  : changement forcé au premier login / après reset (3.2) ;
 *  - failed_login_attempts / locked_until : verrouillage temporaire après échecs (3.2) ;
 *  - last_login_at         : historique de connexion (4.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->timestamp('locked_at')->nullable()->after('is_admin');
            $table->string('locked_reason', 255)->nullable()->after('locked_at');
            $table->boolean('must_change_password')->default(false)->after('locked_reason');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->foreignId('created_by')->nullable()->after('last_login_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'is_admin', 'locked_at', 'locked_reason', 'must_change_password',
                'failed_login_attempts', 'locked_until', 'last_login_at',
            ]);
        });
    }
};
