<?php

namespace App\Exceptions;

/**
 * Pelanggaran aturan penutupan RFC.
 *
 * Mewarisi exception transisi status agar pemanggil lama yang menangkap
 * InvalidRfcStatusTransitionException tetap memperoleh penanganan yang sama.
 */
class RfcClosureException extends InvalidRfcStatusTransitionException {}
