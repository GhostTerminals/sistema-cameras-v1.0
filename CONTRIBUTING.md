# 🤝 Guia de Contribuição

Obrigado por considerar contribuir para o Sistema de Câmeras! Este documento fornece diretrizes e instruções.

## 📋 Código de Conduta

- Seja respeitoso com todos os colaboradores
- Aceite críticas construtivas
- Foque no que é melhor para a comunidade
- Mostre empatia com outros membros da comunidade

## 🚀 Como Contribuir

### Reportar Bugs

Antes de criar um relatório de bug, verifique se o problema já não foi reportado. Se você encontrar um bug:

1. **Use um título claro e descritivo** para o problema
2. **Descreva as etapas exatas** para reproduzir o problema
3. **Forneça exemplos específicos** para demonstrar o problema
4. **Descreva o comportamento observado** e o que você esperava
5. **Inclua capturas de tela/logs** se possível
6. **Mencione seu ambiente**: PHP version, MySQL version, OS, etc.

**Exemplo de bug report:**

```markdown
## Bug: Upload falha para arquivos PDF > 5MB

### Descrição
Quando tento fazer upload de um arquivo PDF maior que 5MB, recebo erro 500.

### Passos para reproduzir
1. Acessar página de cadastro de câmera
2. Clicar em "Anexar documento"
3. Selecionar PDF com 7MB
4. Clicar em "Upload"

### Resultado esperado
Arquivo deve ser uploadado com sucesso

### Resultado atual
Erro 500 - Internal Server Error

### Ambiente
- PHP 8.1.0
- MySQL 8.0.23
- Ubuntu 22.04
```

### Sugerir Melhorias

As sugestões de melhoria podem incluir:

- Novas features
- Melhorias de performance
- Melhorias de UX/UI
- Melhorias de segurança

**Use um título claro** e **descreva o comportamento esperado**.

### Pull Requests

1. **Faça fork do repositório**
2. **Crie uma branch** para sua feature:
   ```bash
   git checkout -b feature/descricao-clara
   ```

3. **Faça commits atômicos** com mensagens claras:
   ```bash
   git commit -m "Adicionar validação de email em cadastro de usuário"
   ```

4. **Siga os padrões de código** (ver abaixo)

5. **Adicione testes** para sua feature:
   ```bash
   # Testes unitários
   tests/Unit/MeuTesteTest.php
   
   # Testes de integração
   tests/Integration/MeuTesteTest.php
   ```

6. **Atualize documentação** se necessário

7. **Execute testes locais**:
   ```bash
   composer test
   ```

8. **Push para sua branch**:
   ```bash
   git push origin feature/descricao-clara
   ```

9. **Abra um Pull Request** com:
   - Título claro e descritivo
   - Descrição do que foi mudado e por quê
   - Referência a issues relacionadas
   - Screenshots/vídeos se for UI change

## 📝 Padrões de Código

### PHP (PSR-12)

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

/**
 * Controlador de usuários
 * 
 * Responsável por gerenciar operações com usuários
 */
class UserController
{
    /**
     * Listar usuários
     * 
     * @return array Lista de usuários
     */
    public function listUsers(): array
    {
        // Implementação
        return [];
    }

    /**
     * Criar novo usuário
     * 
     * @param string $username Nome de usuário
     * @param string $email Email do usuário
     * @return int ID do usuário criado
     * @throws \InvalidArgumentException Se dados inválidos
     */
    public function createUser(string $username, string $email): int
    {
        if (empty($username) || strlen($username) < 3) {
            throw new \InvalidArgumentException('Username deve ter pelo menos 3 caracteres');
        }

        // Implementação
        return 1;
    }
}
```

### Regras PSR-12

- ✅ Use 4 espaços para indentação
- ✅ Máximo 120 caracteres por linha
- ✅ Use `declare(strict_types=1)` no início de arquivos
- ✅ Imports alfabéticos
- ✅ Docstrings em inglês
- ✅ Type hints sempre (parâmetros e retorno)
- ✅ Visibilidade explícita (public, protected, private)

### JavaScript

```javascript
/**
 * Validar campo de email
 * @param {string} email - Email a validar
 * @returns {boolean} True se válido
 */
function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}
```

## ✅ Checklist antes de Submeter PR

- [ ] Código segue PSR-12
- [ ] Testes foram adicionados/atualizados
- [ ] Testes passam localmente (`composer test`)
- [ ] Sem erros PHP (`composer lint`)
- [ ] Documentação foi atualizada
- [ ] Commit messages são claras
- [ ] Sem `.env`, senhas ou credenciais no código
- [ ] Performance foi considerada
- [ ] Segurança foi considerada

## 🧪 Escrevendo Testes

### Teste Unitário

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CalculadoraTest extends TestCase
{
    public function testSoma(): void
    {
        $calc = new Calculadora();
        $resultado = $calc->somar(2, 3);
        
        $this->assertEquals(5, $resultado);
    }

    public function testSomaComValoresNegativos(): void
    {
        $calc = new Calculadora();
        $resultado = $calc->somar(-2, 3);
        
        $this->assertEquals(1, $resultado);
    }
}
```

### Teste de Integração

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class ApiCamerasTest extends TestCase
{
    protected function setUp(): void
    {
        // Setup: criar usuário de teste, conectar ao DB
    }

    public function testListarCameras(): void
    {
        // Fazer request para API
        // Verificar status code 200
        // Verificar estrutura JSON
    }

    protected function tearDown(): void
    {
        // Cleanup: deletar dados de teste
    }
}
```

### Rodar Testes

```bash
# Todos os testes
composer test

# Apenas um arquivo
vendor/bin/phpunit tests/Unit/CalculadoraTest.php

# Apenas um teste
vendor/bin/phpunit tests/Unit/CalculadoraTest.php --filter testSoma

# Com cobertura
vendor/bin/phpunit --coverage-html coverage/
```

## 📚 Recursos Úteis

- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://docs.phpunit.de/)
- [Semantic Versioning](https://semver.org/)
- [Conventional Commits](https://www.conventionalcommits.org/)

## 🎯 Áreas Buscando Contribuições

- [ ] Testes para API endpoints v2
- [ ] Documentação OpenAPI/Swagger
- [ ] Melhorias de performance
- [ ] Tradução para outros idiomas
- [ ] Novos temas/skins
- [ ] Integração com outros sistemas

## ❓ Dúvidas?

- 📧 Email: development@empresa.com
- 💬 Discussions: [GitHub Discussions](https://github.com/YOUR_ORG/sistema-cameras/discussions)
- 📞 Discord: [Servidor da Comunidade](https://discord.gg/...)

---

Obrigado por contribuir! 🎉
