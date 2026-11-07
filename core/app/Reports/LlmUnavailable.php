<?php

namespace App\Reports;

use RuntimeException;

/** Ollama tidak bisa dipakai; LAPSIT dibuat dari template. Pesan tampil di LAPSIT. */
final class LlmUnavailable extends RuntimeException {}
