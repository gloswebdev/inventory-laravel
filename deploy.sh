#!/bin/bash
# ============================================================
#   InvoFlow — Hostinger SSH Deploy Script
#   Usage: bash deploy.sh
#   Run this from inside ~/invoflow/ on Hostinger via SSH
# ============================================================

set -e  # exit on any error

# ── Colors ───────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ── Helpers ──────────────────────────────────────────────────
ok()   { echo -e "${GREEN}  ✅ $1${NC}"; }
warn() { echo -e "${YELLOW}  ⚠️  $1${NC}"; }
info() { echo -e "${CYAN}  ℹ️  $1${NC}"; }
fail() { echo -e "${RED}  ❌ $1${NC}"; exit 1; }
step() { echo -e "\n${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"; echo -e "${BOLD}  $1${NC}"; echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"; }

# ── Banner ───────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}"
echo "  ██╗███╗   ██╗██╗   ██╗ ██████╗ ███████╗██╗      ██████╗ ██╗    ██╗"
echo "  ██║████╗  ██║██║   ██║██╔═══██╗██╔════╝██║     ██╔═══██╗██║    ██║"
echo "  ██║██╔██╗ ██║██║   ██║██║   ██║█████╗  ██║     ██║   ██║██║ █╗ ██║"
echo "  ██║██║╚██╗██║╚██╗ ██╔╝██║   ██║██╔══╝  ██║     ██║   ██║██║███╗██║"
echo "  ██║██║ ╚████║ ╚████╔╝ ╚██████╔╝██║     ███████╗╚██████╔╝╚███╔███╔╝"
echo "  ╚═╝╚═╝  ╚═══╝  ╚═══╝   ╚═════╝ ╚═╝     ╚══════╝ ╚═════╝  ╚══╝╚══╝ "
echo -e "${NC}"
echo -e "  ${BOLD}Hostinger Auto-Deploy Script${NC} | InvoFlow v1.0"
echo ""

# ── Pre-flight checks ────────────────────────────────────────
step "STEP 0 — Pre-flight Checks"

# Check we're in the right directory
if [ ! -f "artisan" ]; then
    fail "artisan file nahi mila! Script ko ~/invoflow/ ke andar se chalao:\n  cd ~/invoflow && bash deploy.sh"
fi
ok "Directory sahi hai (artisan found)"

# Check PHP
PHP_BIN=$(which php8.2 2>/dev/null || which php 2>/dev/null || echo "")
if [ -z "$PHP_BIN" ]; then
    fail "PHP nahi mila! Hostinger SSH mein: export PATH=\$PATH:/usr/local/php82/bin"
fi
PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
info "PHP version: $PHP_VER (using $PHP_BIN)"
if [[ "$PHP_VER" < "8.2" ]]; then
    warn "PHP 8.2+ recommended. hPanel se PHP version change karo."
fi
ok "PHP check passed"

# Check .env exists
if [ ! -f ".env" ]; then
    fail ".env file nahi mili! Pehle .env banao invoflow/ mein (HOSTINGER_DEPLOY_GUIDE.md ka STEP 6 dekho)"
fi
ok ".env file found"

# ── Step 1: Composer Install ──────────────────────────────────
step "STEP 1 — Composer Install (No Dev)"

COMPOSER_BIN=$(which composer2 2>/dev/null || which composer 2>/dev/null || echo "")
if [ -z "$COMPOSER_BIN" ]; then
    info "Composer not found globally. Downloading locally..."
    $PHP_BIN -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP_BIN composer-setup.php --quiet
    rm -f composer-setup.php
    COMPOSER_BIN="$PHP_BIN composer.phar"
    ok "Composer downloaded"
fi

info "Running: composer install --no-dev --optimize-autoloader"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1
ok "Composer install complete"

# ── Step 2: APP_KEY Generate (agar blank ho) ─────────────────
step "STEP 2 — APP_KEY Check"

APP_KEY_VAL=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
if [ -z "$APP_KEY_VAL" ] || [ "$APP_KEY_VAL" == '""' ]; then
    info "APP_KEY blank hai, generate kar raha hoon..."
    $PHP_BIN artisan key:generate --force
    ok "APP_KEY generate kiya"
else
    ok "APP_KEY already set hai"
fi

# ── Step 3: Storage Link ──────────────────────────────────────
step "STEP 3 — Storage Link"

PUBLIC_HTML=$(dirname "$(pwd)")/domains/$(grep "^APP_URL=" .env | sed 's|.*://||' | tr -d '"')/public_html
if [ -d "$PUBLIC_HTML" ]; then
    STORAGE_LINK="$PUBLIC_HTML/storage"
    STORAGE_TARGET="$(pwd)/storage/app/public"

    if [ -L "$STORAGE_LINK" ]; then
        warn "Storage symlink already exists. Removing old one..."
        rm "$STORAGE_LINK"
    fi

    ln -s "$STORAGE_TARGET" "$STORAGE_LINK"
    ok "Storage symlink banaya: $STORAGE_LINK → $STORAGE_TARGET"
else
    warn "public_html nahi mila automatically. Manually storage:link chalao:"
    info "  $PHP_BIN artisan storage:link"
    $PHP_BIN artisan storage:link 2>/dev/null || warn "storage:link fail hua (manually symlink banao)"
fi

# ── Step 4: Permissions ───────────────────────────────────────
step "STEP 4 — Permissions Fix"

chmod -R 755 storage/ 2>/dev/null && ok "storage/ → 755" || warn "storage/ permission set nahi hua"
chmod -R 755 bootstrap/cache/ 2>/dev/null && ok "bootstrap/cache/ → 755" || warn "bootstrap/cache/ permission set nahi hua"

# ── Step 5: Database Migrate ──────────────────────────────────
step "STEP 5 — Database Migration"

info "Kya fresh migration chahiye? (WARNING: sabhi data delete ho jayega)"
read -p "  Type 'fresh' for fresh migrate, Enter for normal migrate: " MIGRATE_TYPE

if [ "$MIGRATE_TYPE" == "fresh" ]; then
    warn "Fresh migration chal rahi hai..."
    $PHP_BIN artisan migrate:fresh --force --seed
    ok "Fresh migration + seeding complete"
else
    $PHP_BIN artisan migrate --force
    ok "Migration complete"
fi

# ── Step 6: Fix index.php for Hostinger ──────────────────────
step "STEP 6 — public_html/index.php Path Fix"

# Find public_html
if [ -d "$PUBLIC_HTML" ]; then
    INDEX_FILE="$PUBLIC_HTML/index.php"
    if [ -f "$INDEX_FILE" ]; then
        # Backup karo
        cp "$INDEX_FILE" "$INDEX_FILE.bak"
        info "Backup banaya: $INDEX_FILE.bak"

        # Sed se fix karo — $appRoot = dirname(__DIR__) ko $appRoot = dirname(__DIR__) . '/invoflow'; se replace karo
        sed -i "s|\\$appRoot = dirname(__DIR__);|\\$appRoot = dirname(__DIR__) . '/invoflow';|g" "$INDEX_FILE"
        # Agar already correct ho to koi fark nahi padega

        ok "index.php patched for Hostinger"
    else
        warn "index.php nahi mila at $INDEX_FILE"
        info "Manually public_html/index.php mein \$appRoot fix karo"
    fi
else
    warn "public_html directory automatically detect nahi hua"
    info "Manually public_html/index.php mein yeh line fix karo:"
    info '  $appRoot = dirname(__DIR__) . '"'"'/invoflow'"'"';'
fi

# ── Step 7: Laravel Cache ─────────────────────────────────────
step "STEP 7 — Laravel Cache Optimize"

$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan cache:clear

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

ok "Cache clear + rebuild complete"

# ── Step 8: Queue Table ───────────────────────────────────────
step "STEP 8 — Queue & Session Tables"

$PHP_BIN artisan queue:table 2>/dev/null && $PHP_BIN artisan migrate --force 2>/dev/null || true
$PHP_BIN artisan session:table 2>/dev/null && $PHP_BIN artisan migrate --force 2>/dev/null || true
ok "Queue/Session tables ready"

# ── Final Summary ─────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║   🎉 DEPLOY COMPLETE! InvoFlow Ready  ║${NC}"
echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════╝${NC}"
echo ""
APP_URL=$(grep "^APP_URL=" .env | cut -d'=' -f2 | tr -d '"')
echo -e "  🌐 URL:     ${BOLD}${APP_URL}${NC}"
echo -e "  📁 App:     ${BOLD}~/invoflow/${NC}"
echo -e "  🗂️  Public:  ${BOLD}~/public_html/${NC} (ya domains/yourdomain/public_html)"
echo ""
echo -e "  ${YELLOW}⚠️  IMPORTANT: Ensure these are done:${NC}"
echo -e "  □ APP_DEBUG=false in .env"
echo -e "  □ SSL enabled in hPanel"
echo -e "  □ DB credentials correct in .env"
echo -e "  □ installer.php deleted from public_html (if used)"
echo ""
