#!/bin/bash
# Script de validação da Fase 2 - Frontend
# Testa Dark Mode, Skeleton Loading, Accessibility, PWA

echo "============================================"
echo "FASE 2 - FRONTEND VALIDATION"
echo "============================================"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Verificar arquivos criados
echo -e "${YELLOW}1. Verificando arquivos criados...${NC}"
files_to_check=(
  "public/assets/css/theme-enhancements.css"
  "public/assets/js/utils/ui/theme-manager.js"
  "public/sw.js"
  "public/assets/snippets/dark-mode-toggle.html"
)

for file in "${files_to_check[@]}"; do
  if [ -f "$file" ]; then
    echo -e "${GREEN}   ✓ $file${NC}"
  else
    echo -e "${RED}   ✗ $file${NC}"
  fi
done

# 2. Verificar integração no header.php
echo ""
echo -e "${YELLOW}2. Verificando integração no header.php...${NC}"

if grep -q "theme-enhancements.css" inc/header.php; then
  echo -e "${GREEN}   ✓ theme-enhancements.css incluído${NC}"
else
  echo -e "${RED}   ✗ theme-enhancements.css NÃO incluído${NC}"
fi

if grep -q "theme-manager.js" inc/header.php; then
  echo -e "${GREEN}   ✓ theme-manager.js incluído${NC}"
else
  echo -e "${RED}   ✗ theme-manager.js NÃO incluído${NC}"
fi

if grep -q "navigator.serviceWorker.register" inc/header.php; then
  echo -e "${GREEN}   ✓ Service Worker registration incluído${NC}"
else
  echo -e "${RED}   ✗ Service Worker registration NÃO incluído${NC}"
fi

# 3. Verificar integração na navbar.php
echo ""
echo -e "${YELLOW}3. Verificando integração na navbar.php...${NC}"

if grep -q "themeToggle" inc/navbar.php; then
  echo -e "${GREEN}   ✓ Dark Mode Toggle incluído${NC}"
else
  echo -e "${RED}   ✗ Dark Mode Toggle NÃO incluído${NC}"
fi

# 4. Validar CSS
echo ""
echo -e "${YELLOW}4. Validando CSS...${NC}"

css_features=(
  "@media (prefers-color-scheme: dark)"
  ".skeleton {"
  "WCAG 2.1"
  "@media (max-width: 575.98px)"
)

for feature in "${css_features[@]}"; do
  if grep -q "$feature" public/assets/css/theme-enhancements.css; then
    echo -e "${GREEN}   ✓ $feature${NC}"
  else
    echo -e "${RED}   ✗ $feature${NC}"
  fi
done

# 5. Validar JavaScript
echo ""
echo -e "${YELLOW}5. Validando JavaScript...${NC}"

js_features=(
  "class ThemeManager"
  "class SkeletonLoader"
  "class AccessibilityHelper"
  "serviceWorker"
)

for feature in "${js_features[@]}"; do
  if grep -q "$feature" public/assets/js/utils/ui/theme-manager.js 2>/dev/null; then
    echo -e "${GREEN}   ✓ $feature${NC}"
  else
    echo -e "${RED}   ✗ $feature${NC}"
  fi
done

# 6. Validar Service Worker
echo ""
echo -e "${YELLOW}6. Validando Service Worker...${NC}"

sw_features=(
  "CACHE_NAME"
  "addEventListener.*install"
  "addEventListener.*fetch"
  "addEventListener.*activate"
)

for feature in "${sw_features[@]}"; do
  if grep -qE "$feature" public/sw.js; then
    echo -e "${GREEN}   ✓ $feature${NC}"
  else
    echo -e "${RED}   ✗ $feature${NC}"
  fi
done

echo ""
echo -e "${YELLOW}7. Testes Recomendados...${NC}"
echo ""
echo "✓ Dark Mode Test:"
echo "  1. Abrir DevTools (F12)"
echo "  2. Ir para Rendering"
echo "  3. Emular 'prefers-color-scheme: dark'"
echo "  4. Verificar se cores mudam"
echo ""
echo "✓ Skeleton Loading Test:"
echo "  Abrir console e executar:"
echo "  window.skeletonLoader.show('element-id', 3000, { type: 'card' })"
echo ""
echo "✓ Service Worker Test:"
echo "  1. DevTools → Application → Service Workers"
echo "  2. Verificar se 'activated and running'"
echo "  3. DevTools → Network → Mark offline"
echo "  4. Tentar acessar página"
echo ""
echo "✓ Accessibility Test:"
echo "  1. DevTools → Lighthouse"
echo "  2. Executar auditoria"
echo "  3. Target: Accessibility score 90+"
echo ""
echo "✓ Performance Test:"
echo "  1. DevTools → Lighthouse"
echo "  2. Run audit"
echo "  3. Targets:"
echo "     - Performance: 80+"
echo "     - Accessibility: 90+"
echo "     - Best Practices: 90+"
echo "     - SEO: 90+"
echo ""

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Validação completa!${NC}"
echo -e "${GREEN}============================================${NC}"
