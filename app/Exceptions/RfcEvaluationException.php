<?php

namespace App\Exceptions;

/**
 * Pelanggaran aturan pencatatan evaluasi RFC.
 *
 * Mewarisi exception transisi status agar pemanggil lama yang menangkap
 * InvalidRfcStatusTransitionException tetap memperoleh penanganan yang sama.
 */
class RfcEvaluationException extends InvalidRfcStatusTransitionException {}
