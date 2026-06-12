<?php
/**
 * Request Validator - Validação centralizada de inputs
 * 
 * Suporta validação baseada em regras:
 * - Primitivas: required, string, numeric, boolean, array
 * - Tamanho: min, max, length
 * - Tipo: email, url, uuid, date, json
 * - Valor: in, not_in, regex, custom
 * 
 * Uso:
 * $validator = new RequestValidator($_POST);
 * $validator->validate([
 *   'nome' => 'required|string|max:100',
 *   'email' => 'required|email',
 *   'idade' => 'required|numeric|min:18|max:150',
 *   'status' => 'required|in:ativo,inativo,pendente'
 * ]);
 * 
 * if ($validator->fails()) {
 *   ApiResponse::validationError($validator->errors());
 * }
 */

class RequestValidator
{
    private array $data;
    private array $errors = [];
    private array $customMessages = [];
    private array $customValidators = [];

    // Aliases para tipos de dados
    private array $typeAliases = [
        'str' => 'string',
        'int' => 'numeric',
        'bool' => 'boolean',
        'arr' => 'array',
        'obj' => 'object',
    ];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function addValidator(string $name, callable $callback): self
    {
        $this->customValidators[$name] = $callback;
        return $this;
    }

    /**
     * Valida dados contra schema de regras
     * 
     * @param array $rules Schema com regras (field => 'rule1|rule2|rule3')
     * @param array $customMessages Mensagens customizadas por campo
     * @return bool True se válido, false se houver erros
     */
    public function validate(array $rules, array $customMessages = []): bool
    {
        $this->errors = [];
        $this->customMessages = $customMessages;

        foreach ($rules as $field => $ruleString) {
            $this->validateField($field, $ruleString);
        }

        return count($this->errors) === 0;
    }

    /**
     * Valida um campo individual
     */
    private function validateField(string $field, string $ruleString): void
    {
        $rules = array_map('trim', explode('|', $ruleString));
        $value = $this->data[$field] ?? null;
        $isRequired = in_array('required', $rules, true);

        // Se o campo não é obrigatório e está vazio, pular validação
        if (!$isRequired && ($value === null || $value === '')) {
            return;
        }

        foreach ($rules as $rule) {
            if (!$this->applyRule($field, $value, $rule)) {
                break; // Parar no primeiro erro
            }
        }
    }

    /**
     * Aplica regra individual
     */
    private function applyRule(string $field, $value, string $rule): bool
    {
        // Extrair regra e parâmetros (ex: max:100 → max e [100])
        $parts = explode(':', $rule, 2);
        $ruleName = trim($parts[0]);
        $params = isset($parts[1]) ? array_map('trim', explode(',', $parts[1])) : [];

        // Substituir aliases
        $ruleName = $this->typeAliases[$ruleName] ?? $ruleName;

        // Aplicar validador customizado se existir
        if (isset($this->customValidators[$ruleName])) {
            $callback = $this->customValidators[$ruleName];
            if (!$callback($field, $value, $params)) {
                $this->addError($field, $ruleName, $params);
                return false;
            }
            return true;
        }

        // Aplicar validador built-in
        $methodName = 'validate' . ucfirst($ruleName);
        if (method_exists($this, $methodName)) {
            if (!$this->$methodName($field, $value, $params)) {
                $this->addError($field, $ruleName, $params);
                return false;
            }
            return true;
        }

        // Regra desconhecida - falhar com erro
        error_log("RequestValidator: regra desconhecida '{$ruleName}' para campo '{$field}'");
        $this->addError($field, $ruleName, $params);
        return false;
    }

    /**
     * Campo obrigatório
     */
    private function validateRequired(string $field, $value): bool
    {
        if (is_string($value)) {
            return strlen(trim($value)) > 0;
        }
        return $value !== null;
    }

    /**
     * String
     */
    private function validateString(string $field, $value): bool
    {
        return is_string($value);
    }

    /**
     * Numérico (int ou float)
     */
    private function validateNumeric(string $field, $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Inteiro
     */
    private function validateInteger(string $field, $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Float/decimal
     */
    private function validateFloat(string $field, $value): bool
    {
        return is_float($value) || is_numeric($value);
    }

    /**
     * Booleano
     */
    private function validateBoolean(string $field, $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false']);
    }

    /**
     * Array
     */
    private function validateArray(string $field, $value): bool
    {
        return is_array($value);
    }

    /**
     * Objeto
     */
    private function validateObject(string $field, $value): bool
    {
        return is_object($value) || (is_string($value) && $this->isJson($value));
    }

    /**
     * Email
     */
    private function validateEmail(string $field, $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * URL
     */
    private function validateUrl(string $field, $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * IP address
     */
    private function validateIp(string $field, $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * UUID v4
     */
    private function validateUuid(string $field, $value): bool
    {
        $uuid4Pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return is_string($value) && preg_match($uuid4Pattern, $value) === 1;
    }

    /**
     * Data em formato específico (padrão: Y-m-d)
     */
    private function validateDate(string $field, $value, array $params = []): bool
    {
        $format = $params[0] ?? 'Y-m-d';
        $date = \DateTime::createFromFormat($format, $value);
        return $date !== false && $date->format($format) === $value;
    }

    /**
     * Data antes de hoje
     */
    private function validateDateBefore(string $field, $value, array $params = []): bool
    {
        if (!$this->validateDate($field, $value)) {
            return false;
        }
        $date = new \DateTime($value);
        return $date < new \DateTime('today');
    }

    /**
     * Data depois de hoje
     */
    private function validateDateAfter(string $field, $value, array $params = []): bool
    {
        if (!$this->validateDate($field, $value)) {
            return false;
        }
        $date = new \DateTime($value);
        return $date > new \DateTime('today');
    }

    /**
     * JSON válido
     */
    private function validateJson(string $field, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Comprimento exato
     */
    private function validateLength(string $field, $value, array $params = []): bool
    {
        if (!isset($params[0])) {
            return true;
        }
        $length = (int)$params[0];
        return mb_strlen((string)$value) === $length;
    }

    /**
     * Comprimento mínimo
     */
    private function validateMin(string $field, $value, array $params = []): bool
    {
        if (!isset($params[0])) {
            return true;
        }
        $min = (int)$params[0];

        if (is_numeric($value)) {
            return $value >= $min;
        }

        return mb_strlen((string)$value) >= $min;
    }

    /**
     * Comprimento máximo
     */
    private function validateMax(string $field, $value, array $params = []): bool
    {
        if (!isset($params[0])) {
            return true;
        }
        $max = (int)$params[0];

        if (is_numeric($value)) {
            return $value <= $max;
        }

        return mb_strlen((string)$value) <= $max;
    }

    /**
     * Valor deve estar em lista
     */
    private function validateIn(string $field, $value, array $params = []): bool
    {
        return in_array($value, $params, true);
    }

    /**
     * Valor NÃO deve estar em lista
     */
    private function validateNotIn(string $field, $value, array $params = []): bool
    {
        return !in_array($value, $params, true);
    }

    /**
     * Regex match
     */
    private function validateRegex(string $field, $value, array $params = []): bool
    {
        if (!isset($params[0])) {
            return true;
        }
        try {
            $result = preg_match($params[0], $value);
            if ($result === false) {
                $errorCode = preg_last_error();
                error_log("RequestValidator: regex error (code $errorCode) for field '$field'");
                return false;
            }
            return $result === 1;
        } catch (Throwable $e) {
            error_log("RequestValidator: regex exception for field '$field': " . $e->getMessage());
            return false;
        }
    }

    /**
     * CPF válido (brasileiro)
     */
    private function validateCpf(string $field, $value): bool
    {
        // Remover caracteres não numéricos
        $value = preg_replace('/\D/', '', (string)$value);

        // CPF deve ter exatamente 11 dígitos
        if (strlen($value) !== 11) {
            return false;
        }

        // CPFs conhecidamente inválidos
        if (preg_match('/^(\d)\1{10}$/', $value)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$value[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int)$value[9] !== $digit1) {
            return false;
        }

        // Validar segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$value[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int)$value[10] === $digit2;
    }

    /**
     * CNPJ válido (brasileiro)
     */
    private function validateCnpj(string $field, $value): bool
    {
        // Remover caracteres não numéricos
        $value = preg_replace('/\D/', '', (string)$value);

        // CNPJ deve ter exatamente 14 dígitos
        if (strlen($value) !== 14) {
            return false;
        }

        // CNPJs conhecidamente inválidos
        if (preg_match('/^(\d)\1{13}$/', $value)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $sum = 0;
        $multiplier = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$value[$i] * $multiplier[$i];
        }

        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int)$value[12] !== $digit1) {
            return false;
        }

        // Validar segundo dígito verificador
        $sum = 0;
        $multiplier = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$value[$i] * $multiplier[$i];
        }

        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int)$value[13] === $digit2;
    }

    /**
     * Verifica se tem erros
     */
    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Verifica se passou
     */
    public function passes(): bool
    {
        return count($this->errors) === 0;
    }

    /**
     * Obtém erros
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Adiciona erro
     */
    private function addError(string $field, string $rule, array $params = []): void
    {
        $message = $this->customMessages[$field][$rule] ?? $this->getDefaultMessage($rule, $field, $params);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Obtém mensagem padrão para regra
     */
    private function getDefaultMessage(string $rule, string $field, array $params = []): string
    {
        $safeField = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');
        $messages = [
            'required' => "O campo '{$safeField}' é obrigatório",
            'string' => "O campo '{$safeField}' deve ser texto",
            'numeric' => "O campo '{$safeField}' deve ser numérico",
            'integer' => "O campo '{$safeField}' deve ser inteiro",
            'float' => "O campo '{$safeField}' deve ser decimal",
            'boolean' => "O campo '{$safeField}' deve ser booleano",
            'array' => "O campo '{$safeField}' deve ser um array",
            'email' => "O campo '{$safeField}' deve ser email válido",
            'url' => "O campo '{$safeField}' deve ser URL válida",
            'ip' => "O campo '{$safeField}' deve ser IP válido",
            'uuid' => "O campo '{$safeField}' deve ser UUID válido",
            'date' => "O campo '{$safeField}' deve ser data válida",
            'json' => "O campo '{$safeField}' deve ser JSON válido",
            'cpf' => "O campo '{$safeField}' deve ser CPF válido",
            'cnpj' => "O campo '{$safeField}' deve ser CNPJ válido",
            'min' => "O campo '{$safeField}' deve ter mínimo " . ($params[0] ?? '0') . " caracteres",
            'max' => "O campo '{$safeField}' deve ter máximo " . ($params[0] ?? 'Infinito') . " caracteres",
            'length' => "O campo '{$safeField}' deve ter exatamente " . ($params[0] ?? '0') . " caracteres",
            'in' => "O campo '{$safeField}' deve ser um dos valores válidos",
            'regex' => "O campo '{$safeField}' não atende ao formato esperado",
        ];

        return $messages[$rule] ?? "O campo '{$safeField}' não passou na validação '{$rule}'";
    }

    /**
     * Verifica se string é JSON válida
     */
    private function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

}
