<?php

namespace App\Policies;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;

class ChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['rfc.view_own', 'rfc.view_all', 'rfc.view_assigned']);
    }

    public function view(User $user, ChangeRequest $rfc): bool
    {
        if ($user->hasPermissionTo('rfc.view_all')) {
            return true;
        }

        if ($user->hasPermissionTo('rfc.view_own') && $rfc->submitted_by_id === $user->id) {
            return true;
        }

        return ! $rfc->trashed()
            && $user->hasPermissionTo('rfc.view_assigned')
            && $user->isReleaseTeam()
            && $rfc->hasReleaseTaskFor($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rfc.create');
    }

    public function update(User $user, ChangeRequest $rfc): bool
    {
        // Pemohon hanya boleh mengedit saat draft atau perlu revisi.
        if (in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true)) {
            return $rfc->submitted_by_id === $user->id;
        }

        return false;
    }

    public function cancel(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        // Pembatalan oleh Pemohon hanya untuk RFC miliknya yang masih dapat direvisi.
        return $rfc->submitted_by_id === $user->id
            && in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true);
    }

    public function delete(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        // Pemohon hanya boleh menghapus RFC miliknya sendiri yang masih Draft.
        return $rfc->submitted_by_id === $user->id
            && RfcStatus::fromValue($rfc->status) === RfcStatus::DRAFT;
    }

    public function restore(User $user, ChangeRequest $rfc): bool
    {
        // Pemulihan adalah kewenangan staf yang boleh melihat seluruh RFC.
        return $rfc->trashed() && $user->hasPermissionTo('rfc.view_all');
    }

    public function reviewCompleteness(User $user): bool
    {
        return $user->hasPermissionTo('rfc.review_completeness');
    }

    public function assessRisk(User $user, ChangeRequest $rfc): bool
    {
        // Aturan Segregation of Duties (SoD): Penilai risiko tidak boleh yang mengajukan RFC.
        if ($rfc->submitted_by_id === $user->id) {
            return false;
        }

        return $user->hasPermissionTo('rfc.assess_risk');
    }

    public function recordDecision(User $user, ChangeRequest $rfc): bool
    {
        // Aturan Segregation of Duties (SoD): Change Manager tidak boleh
        // memutuskan RFC yang ia ajukan sendiri.
        if ($rfc->trashed() || $rfc->submitted_by_id === $user->id) {
            return false;
        }

        return $user->hasPermissionTo('rfc.record_decision')
            && RfcStatus::fromValue($rfc->status) === RfcStatus::TICKET_CREATED;
    }

    public function assignRelease(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed() || ! $user->hasPermissionTo('rfc.assign_release')) {
            return false;
        }

        if (! $user->hasRole('Change Manager') || RfcStatus::fromValue($rfc->status) !== RfcStatus::APPROVED) {
            return false;
        }

        return ! $rfc->releaseTasks()->exists();
    }

    /**
     * Akses halaman daftar disposisi mengikuti kewenangan yang sama dengan
     * pemberian disposisi, sehingga tidak ada menu tanpa tindakan lanjutan.
     */
    public function viewDisposition(User $user): bool
    {
        return $user->hasRole('Change Manager') && $user->hasPermissionTo('rfc.assign_release');
    }

    public function recordExecution(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()
            || ! $user->hasPermissionTo('rfc.record_execution')
            || ! $user->isReleaseTeam()
            || $rfc->submitted_by_id === $user->id) {
            return false;
        }

        if (! in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::APPROVED, RfcStatus::IN_PROGRESS], true)) {
            return false;
        }

        if ($rfc->implementation()->exists()) {
            return false;
        }

        $task = $rfc->releaseTaskFor($user);

        return $task !== null
            && $task->execution_status === 'in_progress';
    }

    public function implement(User $user, ChangeRequest $rfc): bool
    {
        // Implementasi adalah eksekusi rilis, bukan kewenangan disposisi
        // Change Manager. Aturan ini sama dengan pencatatan eksekusi agar
        // pemilik RFC, staf tanpa penugasan, dan Super Admin tanpa tugas tetap
        // ditolak.
        return $this->recordExecution($user, $rfc);
    }

    /**
     * Riwayat penghapusan hanya untuk staf dan pemohon. Tim Rilis tidak
     * memiliki akses ke arsip lintas RFC karena kewenangannya terbatas pada
     * tugas yang diberikan.
     */
    public function viewDeletionHistory(User $user): bool
    {
        return $user->hasAnyPermission(['rfc.view_all', 'rfc.view_own']);
    }

    public function evaluate(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed() || ! $user->hasPermissionTo('rfc.close')) {
            return false;
        }

        if (! in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)) {
            return false;
        }

        return $rfc->implementation()->whereNull('evaluated_at')->exists();
    }

    public function close(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed() || ! $user->hasPermissionTo('rfc.close')) {
            return false;
        }

        if (! in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)) {
            return false;
        }

        return $rfc->implementation()->whereNotNull('evaluated_at')->exists();
    }

    public function comment(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        if (in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::DRAFT, RfcStatus::CLOSED, RfcStatus::REJECTED], true)) {
            return false;
        }

        if ($rfc->submitted_by_id === $user->id) {
            return true;
        }

        if ($user->hasRole('Change Manager')) {
            return true;
        }

        return $user->hasPermissionTo('rfc.view_assigned')
            && $user->isReleaseTeam()
            && $rfc->hasReleaseTaskFor($user);
    }

    /**
     * Catatan internal tidak boleh terlihat oleh Pemohon. Admin dan Super
     * Admin tetap dapat membacanya karena memiliki akses seluruh RFC.
     */
    public function viewInternalComments(User $user, ChangeRequest $rfc): bool
    {
        if (! $this->view($user, $rfc)) {
            return false;
        }

        if ($user->hasRole('Change Manager') || $user->hasPermissionTo('rfc.view_all')) {
            return true;
        }

        return $user->hasPermissionTo('rfc.view_assigned')
            && $user->isReleaseTeam()
            && $rfc->hasReleaseTaskFor($user);
    }

    public function rate(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed() || RfcStatus::fromValue($rfc->status) !== RfcStatus::CLOSED) {
            return false;
        }

        if ($rfc->submitted_by_id !== $user->id) {
            return false;
        }

        return ! $rfc->satisfaction()->exists();
    }

    public function viewSatisfaction(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        if ($rfc->submitted_by_id === $user->id) {
            return true;
        }

        return $user->hasRole('Admin') || $user->hasPermissionTo('rfc.view_all');
    }

    public function exportResult(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        if (! in_array(RfcStatus::fromValue($rfc->status), [RfcStatus::CLOSED, RfcStatus::REJECTED], true)) {
            return false;
        }

        return $this->view($user, $rfc);
    }

    public function viewEvidence(User $user, ChangeRequest $rfc): bool
    {
        if ($rfc->trashed()) {
            return false;
        }

        if ($user->hasPermissionTo('rfc.view_all')) {
            return true;
        }

        return $user->hasPermissionTo('rfc.view_assigned')
            && $user->isReleaseTeam()
            && $rfc->hasReleaseTaskFor($user);
    }
}
