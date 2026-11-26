<?php

namespace App\Exceptions;

use RuntimeException;

/** A source could not be processed. The message is safe to show to the user. */
class SourceProcessingException extends RuntimeException {}
