#!/bin/bash

###############################################################################
# Joomla Development Environment Setup Script
# 
# Purpose: Automates the creation of symlinks required for developing
#          Joomla components in a MAMP environment using git repositories.
#
# Usage: ./setup-joomla-symlinks.sh
#
# This script creates:
# 1. Level 1 symlinks: MAMP directories → git source directories
# 2. Clears Joomla's autoloader cache to force regeneration
#
# See JOOMLA_DEVELOPMENT_SETUP.md for detailed documentation.

# Created by ChatGpt
# 03/08/26 Updated by Claude to make it generic
###############################################################################

set -e  # Exit on first error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
MAMP_ROOT="/html"
GIT_ROOT="/git/ra-mailman"
COMPONENT_NAME="com_ra_mailman"
MANIFEST_NAME="ra_mailman"

# Validate prerequisites
echo -e "${BLUE}=== Joomla Development Setup ===${NC}\n"

if [ ! -d "$MAMP_ROOT" ]; then
    echo -e "${RED}Error: MAMP root directory not found at $MAMP_ROOT${NC}"
    exit 1
fi

if [ ! -d "$GIT_ROOT" ]; then
    echo -e "${RED}Error: Git repository not found at $GIT_ROOT${NC}"
    exit 1
fi

if [ ! -d "$GIT_ROOT/$COMPONENT_NAME" ]; then
    echo -e "${RED}Error: Component directory not found at $GIT_ROOT/$COMPONENT_NAME${NC}"
    exit 1
fi

if [ ! -f "$GIT_ROOT/$COMPONENT_NAME/$MANIFEST_NAME.xml" ]; then
    echo -e "${RED}Error: Manifest file not found at $GIT_ROOT/$COMPONENT_NAME/$MANIFEST_NAME.xml${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Prerequisites validated${NC}\n"

# Function to create symlink with error handling
create_symlink() {
    local target=$1
    local link=$2
    local description=$3
    
    if [ -L "$link" ]; then
        # Already a symlink
        if [ "$(readlink "$link")" = "$target" ]; then
            echo -e "${GREEN}✓${NC} $description (already correct)"
            return 0
        else
            echo -e "${YELLOW}⚠${NC} $description (updating existing symlink)"
            rm "$link"
        fi
    elif [ -e "$link" ]; then
        echo -e "${RED}✗${NC} $description (file exists, not a symlink)"
        return 1
    fi
    
    # Create the symlink
    ln -s "$target" "$link"
    echo -e "${GREEN}✓${NC} $description"
    return 0
}

# STEP 1: Create Level 1 Symlinks (MAMP → Git source)
echo -e "${BLUE}Step 1: Creating Level 1 Symlinks (MAMP → Git source)${NC}"
echo "From: MAMP directories"
echo "To:   Git source directories"
echo ""

create_symlink \
    "$GIT_ROOT/$COMPONENT_NAME/site" \
    "$MAMP_ROOT/components/$COMPONENT_NAME" \
    "Site component symlink"

create_symlink \
    "$GIT_ROOT/$COMPONENT_NAME/administrator" \
    "$MAMP_ROOT/administrator/components/$COMPONENT_NAME" \
    "Administrator component symlink"

echo ""

# STEP 2: Verify all symlinks
echo -e "${BLUE}Step 3: Verifying Symlinks${NC}\n"

verify_symlink() {
    local path=$1
    local description=$2
    
    if [ -L "$path" ]; then
        target=$(readlink "$path")
        echo -e "${GREEN}✓${NC} $description"
        echo "  Link: $path"
        echo "  → Target: $target"
        
        if [ -f "$path" ]; then
            echo -e "  ${GREEN}File readable${NC}"
        else
            echo -e "  ${RED}Target not readable!${NC}"
            return 1
        fi
    else
        echo -e "${RED}✗${NC} $description (not a symlink)"
        return 1
    fi
    echo ""
    return 0
}

verify_symlink "$MAMP_ROOT/components/$COMPONENT_NAME" "MAMP site component"
verify_symlink "$MAMP_ROOT/administrator/components/$COMPONENT_NAME" "MAMP administrator component"
verify_symlink "$GIT_ROOT/$COMPONENT_NAME/site/$MANIFEST_NAME.xml" "Git site manifest"
verify_symlink "$GIT_ROOT/$COMPONENT_NAME/administrator/$MANIFEST_NAME.xml" "Git administrator manifest"

# STEP 3: Clear Joomla autoloader cache
echo -e "${BLUE}Step 4: Clearing Joomla Autoloader Cache${NC}\n"

CACHE_FILE="$MAMP_ROOT/administrator/cache/autoload_psr4.php"

if [ -f "$CACHE_FILE" ]; then
    rm "$CACHE_FILE"
    echo -e "${GREEN}✓${NC} Cleared autoload cache"
    echo "  File: $CACHE_FILE"
    echo "  Cache will regenerate on next page load"
else
    echo -e "${YELLOW}⚠${NC} Cache file not found (already cleared or first run)"
fi

echo ""

# Summary
echo -e "${BLUE}=== Setup Complete ===${NC}\n"
echo -e "Next steps:"
echo -e "1. Load your Joomla site in a browser (site or admin)"
echo -e "2. Check Joomla admin: ${YELLOW}Extensions > Manage${NC}"
echo -e "3. Find ${YELLOW}$COMPONENT_NAME${NC} and enable it"
echo ""
echo -e "For more information, see: ${BLUE}JOOMLA_DEVELOPMENT_SETUP.md${NC}"
echo ""
