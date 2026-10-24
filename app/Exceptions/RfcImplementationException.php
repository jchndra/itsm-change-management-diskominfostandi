<?php

namespace App\Exceptions;

/**
 * Pelanggaran aturan pencatatan implementasi RFC.
 *
 * Mewarisi exception transisi status agar pemanggil lama yang menangkap
 * InvalidRfcStatusTransitionException tetap memperoleh pesan yang ramah.
 */
class RfcImplementationException extends InvalidRfcStatusTransitionException {}
