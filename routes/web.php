<?php

use App\Http\Controllers\ChangeRequestAttachmentController;
use App\Http\Controllers\ImplementationEvidenceController;
use App\Http\Controllers\RfcDocumentController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\ChangeRequests\AssessRisk;
use App\Livewire\ChangeRequests\CloseRfc;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\DeletionHistory;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\EvaluateRfc;
use App\Livewire\ChangeRequests\ImplementChange;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\RecordDecision;
use App\Livewire\ChangeRequests\ReviewCompleteness;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\ChangeRequests\ShowDeletedChangeRequest;
use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\ChangeManagerDashboard;
use App\Livewire\Notifications;
use App\Livewire\Profile;
use App\Livewire\Requester\Dashboard;
use App\Models\User;
use App\Services\DashboardRedirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Modifikasi rute root (/) agar otomatis mengarahkan user sesuai perannya
if (app()->environment('local')) {
    Route::get('/__preview/{email}', function (string $email) {
        Auth::login(User::where('email', $email)->firstOrFail());

        return redirect()->route('change-requests.index');
    });
}

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = Auth::user();

    return redirect()->route(app(DashboardRedirector::class)->routeNameFor($user));
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Dashboard staf
    Route::get('/admin/dashboard', AdminDashboard::class)
        ->middleware('can:viewAdminDashboard')
        ->name('admin.dashboard');

    Route::get('/change-manager/dashboard', ChangeManagerDashboard::class)
        ->middleware('can:viewChangeManagerDashboard')
        ->name('change-manager.dashboard');

    // Rute Dasbor Pemohon (Dikeluarkan dari prefix agar URL lebih bersih)
    Route::get('/requester/dashboard', Dashboard::class)->name('requester.dashboard');

    // Notifikasi & profil pengguna
    Route::get('/notifications', Notifications::class)->name('notifications.index');
    Route::get('/profile', Profile::class)->name('profile.show');

    // Dokumen statis RFC (template surat persetujuan & pedoman)
    Route::get('/rfc-documents/{document}', RfcDocumentController::class)
        ->where('document', '[a-z0-9-]+')
        ->middleware('can:downloadRfcDocument')
        ->name('rfc-documents.download');

    Route::prefix('change-requests')->name('change-requests.')->group(function () {
        Route::get('/', ListChangeRequests::class)->name('index');
        Route::get('/create', CreateChangeRequest::class)
            ->middleware('can:rfc.create')
            ->name('create');
        Route::get('/deletion-history', DeletionHistory::class)->name('deletions.index');
        Route::get('/deletion-history/{rfc}', ShowDeletedChangeRequest::class)
            ->withTrashed()
            ->name('deletions.show');
        Route::get('/{rfc}/edit', EditChangeRequest::class)->name('edit');
        Route::get('/{rfc}/attachments/{attachment}', [ChangeRequestAttachmentController::class, 'show'])
            ->name('attachments.show');
        Route::get('/{rfc}/evidences/{evidence}', [ImplementationEvidenceController::class, 'show'])
            ->middleware('can:viewEvidence,rfc')
            ->name('evidences.show');
        Route::get('/{rfc}', ShowChangeRequest::class)->name('show'); // <-- SEKARANG MENGARAH KE KOMPONEN BARU

        // Rute Verifikasi Admin
        Route::get('/{rfc}/review', ReviewCompleteness::class)
            ->middleware('can:rfc.review_completeness')
            ->name('review');

        // Rute Evaluasi & Otorisasi Change Manager
        Route::get('/{rfc}/assess-risk', AssessRisk::class)
            ->middleware('can:rfc.assess_risk')
            ->name('assess-risk');

        Route::get('/{rfc}/record-decision', RecordDecision::class)
            ->middleware('can:rfc.record_decision')
            ->name('record-decision');

        // Rute Eksekusi Rilis & Penutupan
        Route::get('/{rfc}/implement', ImplementChange::class)
            ->middleware('can:implement,rfc')
            ->name('implement');
        Route::get('/{rfc}/evaluate', EvaluateRfc::class)
            ->middleware('can:evaluate,rfc')
            ->name('evaluate');
        Route::get('/{rfc}/close', CloseRfc::class)
            ->middleware('can:close,rfc')
            ->name('close');
    });
});
