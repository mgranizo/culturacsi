# Settori Menu and Pages Setup

This document explains how to set up the complete Settori mega-navigation structure.

## Overview

The setup script will:
1. Create all Settori pages with proper hierarchy
2. Build the mega-menu structure under the existing "Settori" menu item
3. Link all menu items to their corresponding pages
4. Generate page content with H1, intro paragraph, and child links

## How to Run the Setup

### Option 1: WordPress Admin (Recommended)

1. Log in to WordPress admin
2. Navigate to **Appearance → Settori Setup** (or **Themes → Settori Setup**)
3. Click the **"Run Setup"** button
4. Wait for the confirmation message

### Option 2: WP-CLI

If you have WP-CLI access, run:
```bash
wp settori setup
```

## Menu Structure Created

The following structure will be created under "Settori":

- **Arte**
  - Arti Visive
    - Fotografia e Pittura
    - Scultura
    - Arte Digitale
  - Arti Performative / Danza e Movimento
    - Danza
    - Danza Aerea
    - Break Dance
    - Ginnastica Ritmica
    - Pattinaggio Artistico
    - Tango
  - Musica e Canto
    - Musica
    - Canto
  - Teatro e Spettacolo
    - Teatro e Spettacolo (detail page)
  - Letteratura, Poesia ed Editoria
    - Poesia
    - Editoria
  - Attività Culturali e Locali / Ricreative
    - Attività Culturali e Ricreative
    - Pro Loco
  - Attività Educative e Ricreative per Persone con Disabilità
    - Danza con disabili
    - Attività subacquee inclusive
    - Altre attività artistiche o culturali adattate
  - Attività di Supporto e Volontariato
    - Volontariato, Beneficenza & Protezione Civile
  - Attività Terapeutiche e di Benessere
    - Equitazione e Benessere

- **Ambiente**
  - Ambiente acquatico
    - Attività subacquee
    - Attività velistiche e Surfing
    - Surfing & Kayak
  - Ambiente terrestre
    - Cicloturismo
    - Escursionismo e Trekking
    - Nord Walking
    - Sci & Alpinismo
  - Attività aeree
    - Parapendio e Paracadutismo
    - Volo

- **Valorizzazione del Territorio**
  - Tradizioni Popolari e Identità Locale
    - Attività folkloristiche
    - Rievocazioni Storiche
  - Arti Marziali Storiche e Tradizionali
    - Scherma Antica
  - Giochi di Tradizione e Cultura Strategica
    - Bridge
    - Backgammon
    - Burraco
    - Dama e Scacchi
  - Giochi Storici e Identitari Moderni
    - Subbuteo

- **Culture di nicchia**
  - Cultura Motoristica Storica
    - Auto Storiche
    - Moto d'Epoca
  - Collezionismo e Cultura del Dettaglio
    - Collezionismo
    - Modellismo (statico e dinamico)
  - Cultura Enogastronomica Identitaria
    - Enogastronomia

## Page URLs

Pages are created with the following URL structure:
- `/settori/<top-category>/<sub-category>/<leaf>/`

Examples:
- `/settori/arte/arti-visive/scultura/`
- `/settori/ambiente/ambiente-acquatico/attivita-subacquee/`

## Page Content

Each page includes:
- H1 heading with the page title
- Short intro paragraph describing the sector
- List of child items (if any) with links

## Important Notes

1. **Existing Pages**: If a page with the same title already exists, it will be reused and updated with the correct hierarchy and content.

2. **Menu Location**: The menu is automatically assigned to the "Primary Menu" location.

3. **Settori Parent**: If a "Settori" menu item doesn't exist, it will be created automatically.

4. **Duplicates**: The script handles the "Teatro e Spettacolo" case where parent and child have the same name by using different slugs.

5. **Re-running**: You can safely run the setup multiple times - it will update existing pages and menu items without creating duplicates.

## Files Modified

- `inc/settori-setup.php` - Main setup functions
- `admin-settori-setup.php` - Admin interface
- `functions.php` - Includes the setup files

## Troubleshooting

If the menu doesn't appear:
1. Go to **Appearance → Menus**
2. Verify "Primary Menu" is assigned to the "Primary Menu" location
3. Check that the "Settori" menu item exists and has children

If pages aren't created:
1. Check user permissions (requires `manage_options` capability)
2. Check WordPress debug log for errors
3. Verify the theme is active
