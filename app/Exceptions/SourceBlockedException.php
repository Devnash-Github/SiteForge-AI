<?php

namespace App\Exceptions;

/** A source is not publicly accessible (login wall, bot protection, HTTP 401/403/429/999). */
class SourceBlockedException extends SourceProcessingException {}
