<?php

class DatabaseException extends Exception {
    public function __construct($message = "Erro de banco de dados", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class AuthenticationException extends Exception {
    public function __construct($message = "Erro de autenticação", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class ValidationException extends Exception {
    public function __construct($message = "Erro de validação", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class SessionException extends Exception {
    public function __construct($message = "Erro de sessão", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}