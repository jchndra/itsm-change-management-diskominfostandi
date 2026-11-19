<?php

namespace App\Exceptions;

/**
 * Pelanggaran aturan penolakan RFC oleh Change Manager.
 *
 * Mewarisi exception transisi status agar pemanggil lama yang menangkap
 * InvalidRfcStatusTransitionException tetap memperoleh pesan yang ramah.
 */
class RfcRejectionException extends InvalidRfcStatusTransitionException {}
