<?php

namespace App\Exceptions;

/**
 * Welle rejected the stored credentials or the session token.
 *
 * Separate from WelleException because it is the one failure retrying cannot
 * fix: the user has to reconnect their account. Callers stop, record the
 * reason on the user, and leave the queue alone.
 */
class WelleAuthException extends WelleException {}
