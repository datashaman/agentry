<?php

namespace App\Exceptions;

use RuntimeException;

class GitHubTokenExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Your GitHub token has expired or been revoked. Please reconnect your GitHub account.');
    }
}
